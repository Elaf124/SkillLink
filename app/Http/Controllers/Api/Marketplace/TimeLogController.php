<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\TimeLog;
use Illuminate\Http\Request;

class TimeLogController extends Controller
{
    public function index(Request $request, int $bookingId)
    {
        [$booking] = $this->context($request, $bookingId);

        $logs = $booking->timeLogs()->orderByDesc('logged_at')->get();

        return response()->json([
            'data'    => $logs->map(fn (TimeLog $l) => $this->present($l)),
            'summary' => [
                'default_rate'    => $this->defaultRate($booking),
                'is_hourly'       => $this->isHourly($booking),
                'approved_hours'  => round((float) $logs->where('status', 'approved')->sum('hours_logged'), 2),
                'approved_total'  => round($logs->where('status', 'approved')->sum(fn ($l) => (float) $l->hours_logged * (float) $l->hourly_rate), 2),
                'pending_hours'   => round((float) $logs->where('status', 'pending')->sum('hours_logged'), 2),
            ],
        ]);
    }

    public function store(Request $request, int $bookingId)
    {
        [$booking, $isProvider] = $this->context($request, $bookingId);
        abort_unless($isProvider, 403, 'Only the provider can log time.');
        abort_unless(
            in_array($booking->status, ['accepted', 'in_progress', 'awaiting_confirmation'], true),
            422,
            'Time can only be logged while the booking is active.',
        );

        $validated = $request->validate([
            'hours_logged' => 'required|numeric|min:0.25|max:24',
            'note'         => 'nullable|string|max:500',
            'hourly_rate'  => 'nullable|numeric|min:0',
            'logged_at'    => 'nullable|date',
        ]);

        $log = $booking->timeLogs()->create([
            'hours_logged' => $validated['hours_logged'],
            'note'         => $validated['note'] ?? null,
            'hourly_rate'  => $validated['hourly_rate'] ?? $this->defaultRate($booking),
            'logged_at'    => $validated['logged_at'] ?? now(),
            'status'       => 'pending',
        ]);

        Notification::notify($booking->customer_id, 'booking', 'New time entry',
            "Your provider logged {$log->hours_logged}h on booking #{$booking->id} — review to approve.",
            '/bookings/' . $booking->id);

        return response()->json(['message' => 'Time logged.', 'data' => $this->present($log)], 201);
    }

    public function update(Request $request, int $id)
    {
        $log = TimeLog::with('booking')->findOrFail($id);
        [, $isProvider] = $this->context($request, $log->booking_id);
        abort_unless($isProvider, 403);
        abort_unless($log->status === 'pending', 422, 'Only pending entries can be edited.');

        $validated = $request->validate([
            'hours_logged' => 'sometimes|numeric|min:0.25|max:24',
            'note'         => 'nullable|string|max:500',
            'hourly_rate'  => 'sometimes|numeric|min:0',
            'logged_at'    => 'sometimes|date',
        ]);

        $log->update($validated);

        return response()->json(['message' => 'Time entry updated.', 'data' => $this->present($log->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $log = TimeLog::with('booking')->findOrFail($id);
        [, $isProvider] = $this->context($request, $log->booking_id);
        abort_unless($isProvider, 403);
        abort_unless($log->status === 'pending', 422, 'Only pending entries can be removed.');

        $log->delete();

        return response()->json(['message' => 'Time entry removed.']);
    }

    public function approve(Request $request, int $id)
    {
        return $this->review($request, $id, 'approved');
    }

    public function reject(Request $request, int $id)
    {
        return $this->review($request, $id, 'rejected');
    }

    private function review(Request $request, int $id, string $status)
    {
        $log = TimeLog::with('booking.provider')->findOrFail($id);
        [$booking, , $isCustomer] = $this->context($request, $log->booking_id);
        abort_unless($isCustomer, 403);
        abort_unless($log->status === 'pending', 422, 'This entry has already been reviewed.');

        $log->update(['status' => $status, 'reviewed_at' => now()]);

        Notification::notify($booking->provider?->user_id, 'booking', "Time entry {$status}",
            "The customer {$status} your {$log->hours_logged}h entry on booking #{$booking->id}.",
            '/bookings/' . $booking->id);

        return response()->json(['message' => "Time entry {$status}.", 'data' => $this->present($log->fresh())]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function context(Request $request, int $bookingId): array
    {
        $user = $request->user();
        $booking = Booking::with(['provider', 'service'])->findOrFail($bookingId);

        $isCustomer = $booking->customer_id === $user->id;
        $isProvider = $booking->provider?->user_id === $user->id;
        abort_unless($isCustomer || $isProvider, 403, 'You are not part of this booking.');

        return [$booking, $isProvider, $isCustomer];
    }

    private function isHourly(Booking $booking): bool
    {
        return $booking->service?->price_type === 'hourly';
    }

    private function defaultRate(Booking $booking): float
    {
        if ($this->isHourly($booking)) {
            return (float) $booking->service->price;
        }

        return 0.0;
    }

    private function present(TimeLog $l): array
    {
        return [
            'id'           => $l->id,
            'booking_id'   => $l->booking_id,
            'hours_logged' => (float) $l->hours_logged,
            'hourly_rate'  => (float) $l->hourly_rate,
            'subtotal'     => $l->subtotal,
            'note'         => $l->note,
            'status'       => $l->status,
            'logged_at'    => $l->logged_at?->toISOString(),
            'reviewed_at'  => $l->reviewed_at?->toISOString(),
        ];
    }
}
