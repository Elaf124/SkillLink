<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * The current user's notifications, newest first, plus the unread count.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Notification $n) => [
                'id'      => $n->id,
                'type'    => $n->type,
                'title'   => $n->title,
                'message' => $n->message,
                'link'    => $n->link,
                'isRead'  => $n->is_read,
                'time'    => $n->created_at?->toISOString(),
            ]);

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $user->notifications()->where('is_read', false)->count(),
        ]);
    }

    /**
     * Lightweight unread count for the header bell poll.
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread_count' => $request->user()->notifications()->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        $request->user()->notifications()->where('id', $id)->update(['is_read' => true]);

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->where('is_read', false)->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
