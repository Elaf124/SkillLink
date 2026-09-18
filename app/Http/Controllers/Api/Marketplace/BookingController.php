<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingHistory;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Customer views their own bookings.
     */
    public function myBookingsAsCustomer(Request $request)
    {
        $bookings = Booking::with(['job', 'offer', 'provider.user', 'service'])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($bookings);
    }

    /**
     * Provider views bookings assigned to them.
     */
    public function myBookingsAsProvider(Request $request)
    {
        $providerProfile = $request->user()->providerProfile;

        $bookings = Booking::with(['job', 'offer', 'customer', 'service'])
            ->where('provider_id', $providerProfile->id)
            ->latest()
            ->paginate(20);

        return response()->json($bookings);
    }

    /**
     * View a single booking — either party (customer or provider) can view it.
     */
    public function show(Request $request, int $id)
    {
        $booking = Booking::with(['job', 'offer', 'customer', 'provider.user', 'service', 'history'])
            ->find($id);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        $user = $request->user();
        $isCustomer = $booking->customer_id === $user->id;
        $isProvider = $user->providerProfile && $booking->provider_id === $user->providerProfile->id;

        if (! $isCustomer && ! $isProvider) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // The two parties can see each other's phone number only once the
        // booking is live (provider accepted it) — not while it is still
        // pending, rejected or cancelled.
        $contactUnlocked = in_array($booking->status, ['accepted', 'in_progress', 'awaiting_confirmation', 'completed'], true);
        if (! $contactUnlocked) {
            $booking->provider?->user?->makeHidden(['phone']);
            $booking->customer?->makeHidden(['phone']);
        }

        return response()->json(['data' => $booking]);
    }

    /**
     * Provider accepts a pending booking.
     */
    public function accept(Request $request, int $id)
    {
        $booking = $this->findOwnedByProvider($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'This booking is not pending.'], 422);
        }

        $this->transition($booking, 'accepted', $request->user()->id, 'Provider accepted the booking.');

        return response()->json(['message' => 'Booking accepted.', 'data' => $booking->fresh()]);
    }

    /**
     * Provider rejects a pending booking — reason required.
     */
    public function rejectByProvider(Request $request, int $id)
    {
        $booking = $this->findOwnedByProvider($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'This booking is not pending.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $booking->update(['rejection_reason' => $request->rejection_reason]);
        $this->transition($booking, 'rejected', $request->user()->id, $request->rejection_reason);

        return response()->json(['message' => 'Booking rejected.', 'data' => $booking->fresh()]);
    }

    /**
     * DEPRECATED — the `in_progress` step was removed from the booking lifecycle.
     * The route is kept as a no-op so any lingering client that still calls
     * POST /bookings/{id}/start-progress gets a sane response instead of a 404.
     * Providers now go straight from `accepted` to `awaiting_confirmation`.
     */
    public function startProgress(Request $request, int $id)
    {
        $booking = $this->findOwnedByProvider($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        return response()->json([
            'message' => 'The "in progress" step has been removed. This booking is unchanged.',
            'data'    => $booking->fresh(),
        ]);
    }

    /**
     * Provider marks the work as done — awaiting customer confirmation.
     * IMPORTANT: this does NOT mean payment is released yet.
     */
    public function markAwaitingConfirmation(Request $request, int $id)
    {
        $booking = $this->findOwnedByProvider($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        // `accepted` is the normal source state now; `in_progress` is tolerated
        // for any legacy booking that predates the lifecycle change.
        if (! in_array($booking->status, ['accepted', 'in_progress'])) {
            return response()->json(['message' => 'This booking is not ready to be marked complete.'], 422);
        }

        $this->transition($booking, 'awaiting_confirmation', $request->user()->id, 'Provider marked work as complete, awaiting customer confirmation.');

        return response()->json(['message' => 'Marked as awaiting customer confirmation.', 'data' => $booking->fresh()]);
    }

    /**
     * Customer confirms the work is genuinely done.
     * This is the ONLY transition that should trigger payment release later.
     */
    public function confirmCompletion(Request $request, int $id)
    {
        $booking = $this->findOwnedByCustomer($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        if ($booking->status !== 'awaiting_confirmation') {
            return response()->json(['message' => 'This booking is not awaiting confirmation.'], 422);
        }

        $booking->update(['completed_at' => now()]);
        $this->transition($booking, 'completed', $request->user()->id, 'Customer confirmed completion.');

        // Bump the provider's completed_jobs counter
        $booking->provider()->increment('completed_jobs');

        return response()->json(['message' => 'Booking marked as completed.', 'data' => $booking->fresh()]);
    }

    /**
     * Customer cancels — reason + timestamp required.
     */
    public function cancelByCustomer(Request $request, int $id)
    {
        $booking = $this->findOwnedByCustomer($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        if (in_array($booking->status, ['completed', 'cancelled_by_customer', 'cancelled_by_provider', 'rejected'])) {
            return response()->json(['message' => 'This booking can no longer be cancelled.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $booking->update([
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at'        => now(),
        ]);
        $this->transition($booking, 'cancelled_by_customer', $request->user()->id, $request->cancellation_reason);

        return response()->json(['message' => 'Booking cancelled.', 'data' => $booking->fresh()]);
    }

    /**
     * Provider cancels — reason + timestamp required.
     */
    public function cancelByProvider(Request $request, int $id)
    {
        $booking = $this->findOwnedByProvider($request, $id);
        if ($booking instanceof \Illuminate\Http\JsonResponse) return $booking;

        if (in_array($booking->status, ['completed', 'cancelled_by_customer', 'cancelled_by_provider', 'rejected'])) {
            return response()->json(['message' => 'This booking can no longer be cancelled.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $booking->update([
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at'        => now(),
        ]);
        $this->transition($booking, 'cancelled_by_provider', $request->user()->id, $request->cancellation_reason);

        return response()->json(['message' => 'Booking cancelled.', 'data' => $booking->fresh()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    private function findOwnedByProvider(Request $request, int $id)
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }
        $providerProfile = $request->user()->providerProfile;
        if (! $providerProfile || $booking->provider_id !== $providerProfile->id) {
            return response()->json(['message' => 'Forbidden. This is not your booking.'], 403);
        }
        return $booking;
    }

    private function findOwnedByCustomer(Request $request, int $id)
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }
        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your booking.'], 403);
        }
        return $booking;
    }

    private function transition(Booking $booking, string $newStatus, int $changedBy, string $remarks): void
    {
        $booking->update(['status' => $newStatus]);

        BookingHistory::create([
            'booking_id' => $booking->id,
            'status'     => $newStatus,
            'remarks'    => $remarks,
            'changed_by' => $changedBy,
            'changed_at' => now(),
        ]);

        $this->notifyStatusChange($booking, $newStatus);
    }

    private function notifyStatusChange(Booking $booking, string $newStatus): void
    {
        $booking->loadMissing(['provider', 'job', 'service']);

        $customerId     = $booking->customer_id;
        $providerUserId = $booking->provider?->user_id;
        $title          = $booking->job?->title ?? $booking->service?->title ?? ('Booking #' . $booking->id);
        $link           = '/bookings/' . $booking->id;

        match ($newStatus) {
            'accepted' => Notification::notify($customerId, 'booking', 'Booking accepted',
                "Your provider accepted the booking for \"{$title}\".", $link),
            'rejected' => Notification::notify($customerId, 'booking', 'Booking declined',
                "Your provider declined the booking for \"{$title}\".", $link),
            'awaiting_confirmation' => Notification::notify($customerId, 'booking', 'Work marked complete',
                "The provider marked \"{$title}\" complete. Confirm to release payment.", $link),
            'completed' => Notification::notify($providerUserId, 'booking', 'Payment released',
                "The customer confirmed \"{$title}\". Your payout is on the way.", $link),
            'cancelled_by_customer' => Notification::notify($providerUserId, 'booking', 'Booking cancelled',
                "The customer cancelled \"{$title}\".", $link),
            'cancelled_by_provider' => Notification::notify($customerId, 'booking', 'Booking cancelled',
                "The provider cancelled \"{$title}\".", $link),
            default => null,
        };
    }
    public function store(Request $request, int $serviceId)
{
    $service = \App\Models\Service::with('provider')->find($serviceId);

    if (! $service || $service->service_status !== 'active') {
        return response()->json(['message' => 'Service not available.'], 404);
    }

    if ($service->provider->verification_status !== 'verified') {
        return response()->json(['message' => 'This provider is not currently bookable.'], 422);
    }

    $booking = Booking::create([
        'service_id'         => $service->id,
        'customer_id'        => $request->user()->id,
        'provider_id'        => $service->provider_id,
        'customer_timezone'  => $request->user()->timezone,
        'status'             => 'pending',
        'total_amount'       => $service->price,
    ]);

    BookingHistory::create([
        'booking_id' => $booking->id,
        'status'     => 'pending',
        'remarks'    => 'Booking created directly from service listing.',
        'changed_by' => $request->user()->id,
        'changed_at' => now(),
    ]);

    Notification::notify(
        $service->provider->user_id,
        'booking',
        'New booking request',
        "A customer requested \"{$service->title}\". Review and accept or decline.",
        '/bookings/' . $booking->id,
    );

    return response()->json([
        'message' => 'Booking created successfully.',
        'data'    => $booking->load(['provider.user', 'service']),
    ], 201);
}
}