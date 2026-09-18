<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MilestoneController extends Controller
{
    private const COMMISSION_RATE = 0.10;

    public function index(Request $request, int $bookingId)
    {
        [$booking] = $this->context($request, $bookingId);

        return response()->json([
            'data' => $booking->milestones()
                ->with('payments:id,milestone_id,amount,payment_status,transaction_reference,paid_at')
                ->orderBy('sequence_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Milestone $m) => $this->present($m)),
        ]);
    }

    public function store(Request $request, int $bookingId)
    {
        [$booking, $isProvider] = $this->context($request, $bookingId);
        abort_unless($isProvider, 403, 'Only the provider can propose milestones.');
        $this->assertActive($booking);

        $validated = $request->validate([
            'title'       => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'amount'      => 'required|numeric|min:1',
            'due_date'    => 'nullable|date',
        ]);

        $nextOrder = (int) $booking->milestones()->max('sequence_order') + 1;

        $milestone = $booking->milestones()->create([
            'title'          => $validated['title'],
            'description'    => $validated['description'] ?? null,
            'amount'         => $validated['amount'],
            'due_date'       => $validated['due_date'] ?? null,
            'sequence_order' => $nextOrder,
            'status'         => 'pending',
        ]);

        Notification::notify($booking->customer_id, 'booking', 'New milestone proposed',
            "Your provider added the milestone \"{$milestone->title}\" (ETB " . number_format((float) $milestone->amount) . ").",
            '/bookings/' . $booking->id);

        return response()->json(['message' => 'Milestone added.', 'data' => $this->present($milestone)], 201);
    }

    public function update(Request $request, int $id)
    {
        $milestone = Milestone::with('booking')->findOrFail($id);
        [$booking, $isProvider] = $this->context($request, $milestone->booking_id);
        abort_unless($isProvider, 403);
        abort_unless($milestone->status === 'pending', 422, 'Only pending milestones can be edited.');

        $validated = $request->validate([
            'title'       => 'sometimes|string|max:150',
            'description' => 'nullable|string|max:1000',
            'amount'      => 'sometimes|numeric|min:1',
            'due_date'    => 'nullable|date',
        ]);

        $milestone->update($validated);

        return response()->json(['message' => 'Milestone updated.', 'data' => $this->present($milestone->fresh('payments'))]);
    }

    public function destroy(Request $request, int $id)
    {
        $milestone = Milestone::with('booking')->findOrFail($id);
        [, $isProvider] = $this->context($request, $milestone->booking_id);
        abort_unless($isProvider, 403);
        abort_unless($milestone->status === 'pending', 422, 'Only pending milestones can be removed.');

        $milestone->delete();

        return response()->json(['message' => 'Milestone removed.']);
    }

    /** Provider marks a milestone as delivered. */
    public function complete(Request $request, int $id)
    {
        $milestone = Milestone::with('booking')->findOrFail($id);
        [$booking, $isProvider] = $this->context($request, $milestone->booking_id);
        abort_unless($isProvider, 403);
        abort_unless(in_array($milestone->status, ['pending', 'in_progress'], true), 422, 'This milestone cannot be marked complete.');

        $milestone->update(['status' => 'completed', 'completed_at' => now()]);

        Notification::notify($booking->customer_id, 'booking', 'Milestone delivered',
            "\"{$milestone->title}\" is ready for your review and payment.", '/bookings/' . $booking->id);

        return response()->json(['message' => 'Milestone marked complete.', 'data' => $this->present($milestone->fresh('payments'))]);
    }

    /** Customer approves a completed milestone (pre-payment sign-off). */
    public function approve(Request $request, int $id)
    {
        $milestone = Milestone::with('booking')->findOrFail($id);
        [$booking, , $isCustomer] = $this->context($request, $milestone->booking_id);
        abort_unless($isCustomer, 403);
        abort_unless($milestone->status === 'completed', 422, 'Only a completed milestone can be approved.');

        $milestone->update(['status' => 'approved']);

        Notification::notify($booking->provider?->user_id, 'booking', 'Milestone approved',
            "The customer approved \"{$milestone->title}\" — awaiting payment.", '/bookings/' . $booking->id);

        return response()->json(['message' => 'Milestone approved.', 'data' => $this->present($milestone->fresh('payments'))]);
    }

    /** Customer pays an approved milestone — mirrors PaymentController::pay. */
    public function pay(Request $request, int $id)
    {
        $milestone = Milestone::with('booking.provider')->findOrFail($id);
        [$booking, , $isCustomer] = $this->context($request, $milestone->booking_id);
        abort_unless($isCustomer, 403);
        abort_unless($milestone->status === 'approved' && ! $milestone->paid_at, 422, 'This milestone is not payable.');

        $validated = $request->validate([
            'payment_method' => 'required|in:chapa_sim,telebirr_sim,cbe_birr_sim,cash_sim',
        ]);

        $payment = DB::transaction(function () use ($booking, $milestone, $validated) {
            $amount = (float) $milestone->amount;
            $commission = round($amount * self::COMMISSION_RATE, 2);
            $providerAmount = round($amount - $commission, 2);

            $payment = Payment::create([
                'booking_id'            => $booking->id,
                'milestone_id'          => $milestone->id,
                'amount'                => $amount,
                'commission'            => $commission,
                'provider_amount'       => $providerAmount,
                'payment_method'        => $validated['payment_method'],
                'payment_status'        => 'paid',
                'transaction_reference' => 'TXN-' . strtoupper(uniqid()),
                'paid_at'               => now(),
            ]);

            $wallet = Wallet::firstOrCreate(['provider_id' => $booking->provider_id], ['balance' => 0]);
            $wallet->increment('balance', $providerAmount);

            WalletTransaction::create([
                'wallet_id'         => $wallet->id,
                'booking_id'        => $booking->id,
                'type'              => 'credit',
                'amount'            => $providerAmount,
                'description'       => "Milestone \"{$milestone->title}\" for booking #{$booking->id}",
                'gateway_simulated' => true,
            ]);

            $milestone->update(['status' => 'approved', 'paid_at' => now()]);

            return $payment;
        });

        Notification::notify($booking->provider?->user_id, 'payment', 'Milestone paid',
            'ETB ' . number_format((float) $payment->provider_amount) . " for \"{$milestone->title}\" landed in your wallet.",
            '/provider/wallet');

        return response()->json(['message' => 'Milestone paid.', 'data' => $this->present($milestone->fresh('payments'))]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array{0: Booking, 1: bool $isProvider, 2: bool $isCustomer} */
    private function context(Request $request, int $bookingId): array
    {
        $user = $request->user();
        $booking = Booking::with('provider')->findOrFail($bookingId);

        $isCustomer = $booking->customer_id === $user->id;
        $isProvider = $booking->provider?->user_id === $user->id;
        abort_unless($isCustomer || $isProvider, 403, 'You are not part of this booking.');

        return [$booking, $isProvider, $isCustomer];
    }

    private function assertActive(Booking $booking): void
    {
        abort_unless(
            in_array($booking->status, ['accepted', 'in_progress', 'awaiting_confirmation'], true),
            422,
            'Milestones can only be added while the booking is active.',
        );
    }

    private function present(Milestone $m): array
    {
        $paidPayment = $m->relationLoaded('payments')
            ? $m->payments->firstWhere('payment_status', 'paid')
            : $m->payments()->where('payment_status', 'paid')->first();

        return [
            'id'             => $m->id,
            'booking_id'     => $m->booking_id,
            'title'          => $m->title,
            'description'    => $m->description,
            'amount'         => (float) $m->amount,
            'sequence_order' => $m->sequence_order,
            'status'         => $m->status,
            'is_paid'        => (bool) $m->paid_at,
            'due_date'       => $m->due_date?->toDateString(),
            'completed_at'   => $m->completed_at?->toISOString(),
            'paid_at'        => $m->paid_at?->toISOString(),
            'payment_reference' => $paidPayment?->transaction_reference ?? null,
        ];
    }
}
