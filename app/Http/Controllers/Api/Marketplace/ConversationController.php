<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    /**
     * List every conversation the current user is part of — as the customer,
     * or as the provider — newest activity first.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $conversations = $this->baseQuery($user)
            ->with(['latestMessage', 'booking.job:id,title,budget', 'booking.service:id,title,price'])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('is_read', false)->where('sender_id', '!=', $user->id);
            }])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'data' => $conversations->map(fn (Conversation $c) => $this->thread($c, $user))->values(),
        ]);
    }

    /**
     * Customer opens (or re-opens) a conversation with a provider.
     * Idempotent on the (customer, provider) pair.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role?->name === 'provider') {
            return response()->json(['message' => 'Providers reply within existing conversations.'], 403);
        }

        $validated = $request->validate([
            'provider_id' => 'required|integer|exists:provider_profiles,id',
            'booking_id'  => 'nullable|integer|exists:bookings,id',
        ]);

        $conversation = Conversation::firstOrCreate(
            ['customer_id' => $user->id, 'provider_id' => $validated['provider_id']],
            ['booking_id' => $validated['booking_id'] ?? null],
        );

        // Attach booking context to an existing context-less conversation.
        if (! $conversation->booking_id && ! empty($validated['booking_id'])) {
            $conversation->update(['booking_id' => $validated['booking_id']]);
        }

        $conversation->load(['customer', 'provider.user', 'latestMessage', 'booking.job', 'booking.service']);
        $conversation->loadCount(['messages as unread_count' => function ($q) use ($user) {
            $q->where('is_read', false)->where('sender_id', '!=', $user->id);
        }]);

        return response()->json(['data' => $this->thread($conversation, $user)], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Messages in a conversation (oldest first).
     */
    public function messages(Request $request, int $id)
    {
        $user = $request->user();
        $conversation = $this->authorizedConversation($user, $id);

        $messages = $conversation->messages()
            ->with('sender:id,first_name,last_name')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $messages->map(fn (Message $m) => $this->message($m, $conversation, $user))->values(),
        ]);
    }

    /**
     * Send a message (text or proposal).
     */
    public function sendMessage(Request $request, int $id)
    {
        $user = $request->user();
        $conversation = $this->authorizedConversation($user, $id);

        $validated = $request->validate([
            'message'     => 'required|string|max:5000',
            'type'        => 'nullable|in:text,proposal',
            'meta'        => 'nullable|array',
            'meta.amount' => 'nullable|numeric|min:0',
            'meta.note'   => 'nullable|string|max:2000',
            'meta.serviceTitle' => 'nullable|string|max:255',
        ]);

        $type = $validated['type'] ?? 'text';
        $meta = null;

        if ($type === 'proposal') {
            $meta = [
                'amount'       => (float) ($validated['meta']['amount'] ?? 0),
                'note'         => $validated['meta']['note'] ?? '',
                'status'       => 'pending',
                'serviceTitle' => $validated['meta']['serviceTitle'] ?? null,
            ];
        }

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'message'   => $validated['message'],
            'type'      => $type,
            'meta'      => $meta,
            'is_read'   => false,
        ]);

        $message->load('sender:id,first_name,last_name');

        // Notify the other party.
        $conversation->loadMissing('provider:id,user_id');
        $recipientId = $this->viewerIsCustomer($conversation, $user)
            ? $conversation->provider?->user_id
            : $conversation->customer_id;
        $senderName = trim($user->first_name . ' ' . $user->last_name) ?: 'Someone';
        Notification::notify(
            $recipientId,
            'message',
            "New message from {$senderName}",
            $type === 'proposal'
                ? 'Sent you a price proposal of ETB ' . number_format((float) ($meta['amount'] ?? 0)) . '.'
                : \Illuminate\Support\Str::limit($validated['message'], 120),
            '/messages',
        );

        return response()->json(['data' => $this->message($message, $conversation, $user)], 201);
    }

    /**
     * Mark every incoming message in a conversation as read.
     */
    public function markRead(Request $request, int $id)
    {
        $user = $request->user();
        $conversation = $this->authorizedConversation($user, $id);

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read.']);
    }

    /**
     * Accept or decline a price proposal. Appends a system message.
     */
    public function respondToProposal(Request $request, int $id, int $messageId)
    {
        $user = $request->user();
        $conversation = $this->authorizedConversation($user, $id);

        $validated = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        $proposal = $conversation->messages()->where('id', $messageId)->where('type', 'proposal')->firstOrFail();

        if ($proposal->sender_id === $user->id) {
            return response()->json(['message' => 'You cannot respond to your own proposal.'], 403);
        }

        $meta = $proposal->meta ?? [];
        if (($meta['status'] ?? 'pending') !== 'pending') {
            return response()->json(['message' => 'This proposal has already been answered.'], 409);
        }

        DB::transaction(function () use ($proposal, $conversation, $validated, &$meta) {
            $meta['status'] = $validated['status'];
            $proposal->update(['meta' => $meta]);

            $amount = number_format((float) ($meta['amount'] ?? 0));
            $conversation->messages()->create([
                'sender_id' => $proposal->sender_id, // keep the thread's side attribution sane
                'message'   => $validated['status'] === 'accepted'
                    ? "Proposal of ETB {$amount} was accepted."
                    : "Proposal of ETB {$amount} was declined.",
                'type'      => 'system',
                'is_read'   => false,
            ]);
        });

        Notification::notify(
            $proposal->sender_id,
            'message',
            'Proposal ' . $validated['status'],
            'Your price proposal of ETB ' . number_format((float) ($meta['amount'] ?? 0)) . " was {$validated['status']}.",
            '/messages',
        );

        $messages = $conversation->messages()->with('sender:id,first_name,last_name')->orderBy('created_at')->get();

        return response()->json([
            'data' => $messages->map(fn (Message $m) => $this->message($m, $conversation, $user))->values(),
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function baseQuery(User $user)
    {
        $query = Conversation::query()->with(['customer:id,first_name,last_name,profile_photo', 'provider.user:id,first_name,last_name,profile_photo', 'provider:id,user_id,professional_title']);

        if ($user->role?->name === 'provider') {
            $providerProfileId = $user->providerProfile?->id;
            return $query->where('provider_id', $providerProfileId);
        }

        return $query->where('customer_id', $user->id);
    }

    private function authorizedConversation(User $user, int $id): Conversation
    {
        $conversation = Conversation::with(['customer', 'provider.user', 'booking.job', 'booking.service'])->findOrFail($id);

        $isCustomer = $conversation->customer_id === $user->id;
        $isProvider = $conversation->provider?->user_id === $user->id;

        abort_unless($isCustomer || $isProvider, 403, 'You are not part of this conversation.');

        return $conversation;
    }

    private function viewerIsCustomer(Conversation $c, User $user): bool
    {
        return $c->customer_id === $user->id;
    }

    private function thread(Conversation $c, User $user): array
    {
        $viewerIsCustomer = $this->viewerIsCustomer($c, $user);
        $last = $c->relationLoaded('latestMessage') ? $c->latestMessage : $c->messages()->latest()->first();

        if ($viewerIsCustomer) {
            $other = $c->provider?->user;
            $participantId = $c->provider_id;
            $participantRole = 'provider';
            $participantTitle = $c->provider?->professional_title ?: 'Service Provider';
        } else {
            $other = $c->customer;
            $participantId = $c->customer_id;
            $participantRole = 'customer';
            $participantTitle = 'Customer';
        }

        $name = trim(($other->first_name ?? '') . ' ' . ($other->last_name ?? '')) ?: 'SkillLink User';

        return [
            'id'               => $c->id,
            'participantId'    => $participantId,
            'participantName'  => $name,
            'participantRole'  => $participantRole,
            'participantTitle' => $participantTitle,
            'avatarText'       => $this->initials($name),
            'lastMessage'      => $last?->type === 'proposal'
                ? 'Price offer: ETB ' . number_format((float) ($last->meta['amount'] ?? 0))
                : ($last?->message ?? 'No messages yet'),
            'lastMessageTime'  => ($last?->created_at ?? $c->updated_at)?->toISOString(),
            'unreadCount'      => (int) ($c->unread_count ?? 0),
            'context'          => $this->context($c),
            'createdAt'        => $c->created_at?->toISOString(),
            'updatedAt'        => $c->updated_at?->toISOString(),
        ];
    }

    private function message(Message $m, Conversation $c, User $user): array
    {
        $senderIsCustomer = $m->sender_id === $c->customer_id;
        $senderName = $m->relationLoaded('sender') && $m->sender
            ? trim($m->sender->first_name . ' ' . $m->sender->last_name)
            : 'SkillLink User';

        return [
            'id'         => $m->id,
            'threadId'   => $c->id,
            'senderId'   => $m->sender_id,
            'senderName' => $m->type === 'system' ? 'System' : $senderName,
            'senderRole' => $m->type === 'system' ? 'system' : ($senderIsCustomer ? 'customer' : 'provider'),
            'text'       => $m->message,
            'type'       => $m->type,
            'proposalData' => $m->type === 'proposal' && $m->meta ? [
                'id'           => (string) $m->id,
                'amount'       => (float) ($m->meta['amount'] ?? 0),
                'note'         => $m->meta['note'] ?? '',
                'status'       => $m->meta['status'] ?? 'pending',
                'serviceTitle' => $m->meta['serviceTitle'] ?? null,
            ] : null,
            'timestamp'  => $m->created_at?->toISOString(),
            'isMe'       => $m->sender_id === $user->id && $m->type !== 'system',
        ];
    }

    private function context(Conversation $c): array
    {
        if ($c->booking) {
            $title = $c->booking->job?->title ?? $c->booking->service?->title ?? ('Booking #' . $c->booking->id);
            $amount = $c->booking->job?->budget ?? $c->booking->service?->price;

            return [
                'type'   => 'booking',
                'id'     => $c->booking_id,
                'title'  => $title,
                'amount' => $amount !== null ? (float) $amount : null,
            ];
        }

        return ['type' => 'general'];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name));
        $a = mb_substr($parts[0] ?? 'U', 0, 1);
        $b = mb_substr($parts[1] ?? '', 0, 1);

        return mb_strtoupper($a . $b);
    }
}
