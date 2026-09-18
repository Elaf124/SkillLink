<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Job;
use App\Models\Booking;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    /**
     * Provider submits an offer on an open job.
     */
    public function store(Request $request, int $jobId)
    {
        $job = Job::find($jobId);

        if (! $job) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        if ($job->status !== 'open') {
            return response()->json(['message' => 'This job is no longer accepting offers.'], 422);
        }

        $providerProfile = $request->user()->providerProfile;

        // A provider should only be able to submit ONE offer per job
        $existingOffer = Offer::where('job_id', $jobId)
            ->where('provider_id', $providerProfile->id)
            ->first();

        if ($existingOffer) {
            return response()->json(['message' => 'You have already submitted an offer for this job.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'proposed_price'  => 'required|numeric|min:0',
            'message'         => 'nullable|string',
            'estimated_days'  => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $offer = Offer::create(array_merge($validator->validated(), [
            'job_id'      => $jobId,
            'provider_id' => $providerProfile->id,
            'status'      => 'pending',
        ]));

        Notification::notify(
            $job->customer_id,
            'offer',
            'New offer received',
            "A provider offered ETB " . number_format((float) $offer->proposed_price) . " on \"{$job->title}\".",
            '/jobs/' . $job->id,
        );

        return response()->json([
            'message' => 'Offer submitted successfully',
            'data'    => $offer,
        ], 201);
    }

    /**
     * Customer views all offers on their OWN job.
     */
    public function index(Request $request, int $jobId)
    {
        $job = Job::find($jobId);

        if (! $job) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        if ($job->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your job.'], 403);
        }

        $offers = Offer::with(['provider.user'])
            ->where('job_id', $jobId)
            ->get();

        return response()->json(['data' => $offers]);
    }

    /**
     * Customer accepts an offer — this creates a Booking automatically
     * and closes the job + rejects all other offers on it.
     */
    public function accept(Request $request, int $offerId)
    {
        $offer = Offer::with('job')->find($offerId);

        if (! $offer) {
            return response()->json(['message' => 'Offer not found.'], 404);
        }

        if ($offer->job->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your job.'], 403);
        }

        if ($offer->status !== 'pending') {
            return response()->json(['message' => 'This offer is no longer pending.'], 422);
        }

        if ($offer->job->status !== 'open') {
            return response()->json(['message' => 'This job is no longer open.'], 422);
        }

        // Providers whose offers are about to be auto-rejected (for notifications).
        $rejectedProviderUserIds = Offer::with('provider:id,user_id')
            ->where('job_id', $offer->job_id)
            ->where('id', '!=', $offer->id)
            ->where('status', 'pending')
            ->get()
            ->pluck('provider.user_id')
            ->filter()
            ->all();

        $booking = DB::transaction(function () use ($offer, $request) {
            // Accept this one
            $offer->update(['status' => 'accepted']);

            // Reject all other pending offers on the same job
            Offer::where('job_id', $offer->job_id)
                ->where('id', '!=', $offer->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            // Close the job
            $offer->job->update(['status' => 'in_progress']);

            // Create the booking
            return Booking::create([
                'job_id'             => $offer->job_id,
                'offer_id'           => $offer->id,
                'customer_id'        => $request->user()->id,
                'provider_id'        => $offer->provider_id,
                'service_id'         => null,
                'customer_timezone'  => $request->user()->timezone,
                'status'             => 'pending',
                'total_amount'       => $offer->proposed_price,
            ]);
        });

        $offer->loadMissing('provider:id,user_id');
        Notification::notify(
            $offer->provider?->user_id,
            'offer',
            'Offer accepted',
            "Your offer on \"{$offer->job->title}\" was accepted. A booking has been created.",
            '/bookings/' . $booking->id,
        );
        foreach ($rejectedProviderUserIds as $uid) {
            Notification::notify($uid, 'offer', 'Offer not selected',
                "The customer chose another provider for \"{$offer->job->title}\".", '/jobs/' . $offer->job_id);
        }

        return response()->json([
            'message' => 'Offer accepted. Booking created.',
            'data'    => $booking->load(['job', 'offer', 'customer', 'provider']),
        ], 201);
    }

    /**
     * Customer explicitly rejects a specific offer (optional — doesn't close the job).
     */
    public function reject(Request $request, int $offerId)
    {
        $offer = Offer::with('job')->find($offerId);

        if (! $offer) {
            return response()->json(['message' => 'Offer not found.'], 404);
        }

        if ($offer->job->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your job.'], 403);
        }

        if ($offer->status !== 'pending') {
            return response()->json(['message' => 'This offer is no longer pending.'], 422);
        }

        $offer->update(['status' => 'rejected']);

        $offer->loadMissing('provider:id,user_id');
        Notification::notify(
            $offer->provider?->user_id,
            'offer',
            'Offer declined',
            "Your offer on \"{$offer->job->title}\" was declined by the customer.",
            '/jobs/' . $offer->job_id,
        );

        return response()->json(['message' => 'Offer rejected.']);
    }
}