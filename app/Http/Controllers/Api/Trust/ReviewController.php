<?php

namespace App\Http\Controllers\Api\Trust;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Customer leaves a review — ONLY allowed if booking.status === 'completed'.
     */
    public function store(Request $request, int $bookingId)
    {
        $booking = Booking::find($bookingId);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your booking.'], 403);
        }

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'You can only review a completed booking.'], 422);
        }

        $existingReview = Review::where('booking_id', $bookingId)->first();
        if ($existingReview) {
            return response()->json(['message' => 'You have already reviewed this booking.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'communication_rating'    => 'required|integer|min:1|max:5',
            'quality_rating'          => 'required|integer|min:1|max:5',
            'timeliness_rating'       => 'required|integer|min:1|max:5',
            'professionalism_rating'  => 'required|integer|min:1|max:5',
            'comment'                 => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $overallRating = round(
            ($data['communication_rating'] + $data['quality_rating'] + $data['timeliness_rating'] + $data['professionalism_rating']) / 4,
            2
        );

        $review = Review::create(array_merge($data, [
            'booking_id'     => $booking->id,
            'customer_id'    => $request->user()->id,
            'provider_id'    => $booking->provider_id,
            'overall_rating' => $overallRating,
        ]));

        // Recalculate the provider's cached average_rating across all their reviews
        $this->recalculateProviderRating($booking->provider_id);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data'    => $review,
        ], 201);
    }

    /**
     * Public — view all reviews for a specific provider.
     */
    public function forProvider(int $providerId)
    {
        $reviews = Review::with(['customer'])
            ->where('provider_id', $providerId)
            ->latest()
            ->paginate(20);

        return response()->json($reviews);
    }

    /**
     * Provider responds to a review left about them.
     */
    public function respond(Request $request, int $reviewId)
    {
        $review = Review::find($reviewId);

        if (! $review) {
            return response()->json(['message' => 'Review not found.'], 404);
        }

        $providerProfile = $request->user()->providerProfile;
        if (! $providerProfile || $review->provider_id !== $providerProfile->id) {
            return response()->json(['message' => 'Forbidden. This review is not about you.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'provider_response' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $review->update(['provider_response' => $request->provider_response]);

        return response()->json(['message' => 'Response added.', 'data' => $review->fresh()]);
    }

    /**
     * Recalculate and cache the provider's average_rating from all their reviews.
     */
    private function recalculateProviderRating(int $providerId): void
    {
        $average = Review::where('provider_id', $providerId)->avg('overall_rating');

        \App\Models\ProviderProfile::where('id', $providerId)
            ->update(['average_rating' => round($average, 2)]);
    }
}