<?php

namespace App\Http\Controllers\Api\Identity;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProviderProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->providerProfile()
            ->with(['user', 'services', 'skills', 'portfolio', 'availability'])
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'Provider profile not found.'], 404);
        }

        return response()->json(['data' => $profile]);
    }
    public function update(Request $request)
    {
        $profile = $request->user()->providerProfile;

        if (! $profile) {
            return response()->json(['message' => 'Provider profile not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'business_name'       => 'sometimes|string|max:150',
            'bio'                 => 'sometimes|string',
            'professional_title'  => 'sometimes|string|max:150',
            'experience_years'    => 'sometimes|integer|min:0',
            'latitude'            => 'sometimes|numeric|between:-90,90',
            'longitude'           => 'sometimes|numeric|between:-180,180',
            'skills'              => 'sometimes|array',
            'skills.*.id'         => 'required_with:skills|integer|exists:skills,id',
            'skills.*.proficiency_level' => 'nullable|in:beginner,intermediate,expert',
            'availability'        => 'sometimes|array',
            'availability.*.day_of_week' => 'required_with:availability|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'availability.*.start_time'  => 'nullable|date_format:H:i',
            'availability.*.end_time'    => 'nullable|date_format:H:i',
            'availability.*.is_available' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->input('availability', []) as $slot) {
            if (! empty($slot['start_time']) && ! empty($slot['end_time']) && $slot['end_time'] <= $slot['start_time']) {
                return response()->json(['message' => 'Validation failed', 'errors' => ['availability' => ['Each availability end time must be after its start time.']]], 422);
            }
        }

        $data = $validator->validated();
        $profile->update(collect($data)->except(['skills', 'availability'])->all());

        if (array_key_exists('skills', $data)) {
            $profile->skills()->sync(collect($data['skills'])->mapWithKeys(fn ($skill) => [
                $skill['id'] => ['proficiency_level' => $skill['proficiency_level'] ?? 'beginner'],
            ])->all());
        }

        if (array_key_exists('availability', $data)) {
            $profile->availability()->delete();
            $profile->availability()->createMany($data['availability']);
        }

        return response()->json([
            'message' => 'Provider profile updated successfully',
            'data'    => $profile->fresh(['user', 'services', 'skills', 'portfolio', 'availability']),
        ]);
    }

    public function storePortfolio(Request $request)
    {
        $profile = $request->user()->providerProfile;
        if (! $profile) return response()->json(['message' => 'Provider profile not found.'], 404);

        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'image' => 'required|image|max:5120',
        ]);

        $path = $request->file('image')->store('provider-portfolios', 'public');
        $item = $profile->portfolio()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image_url' => Storage::url($path),
        ]);

        return response()->json(['message' => 'Portfolio item uploaded.', 'data' => $item], 201);
    }

    public function destroyPortfolio(Request $request, int $id)
    {
        $profile = $request->user()->providerProfile;
        $item = $profile?->portfolio()->find($id);
        if (! $item) return response()->json(['message' => 'Portfolio item not found.'], 404);

        if ($item->image_url) Storage::disk('public')->delete(str_replace('/storage/', '', $item->image_url));
        $item->delete();
        return response()->json(['message' => 'Portfolio item deleted.']);
    }
    public function showPublic(int $id)
    {
        $profile = ProviderProfile::with(['user', 'services', 'skills', 'portfolio', 'availability'])
            ->where('id', $id)
            ->where('verification_status', 'verified')
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'Provider not found or not verified.'], 404);
        }

        // Contact details are not public — a customer only gets the provider's
        // phone once they have an accepted booking together (see BookingController::show).
        $profile->user?->makeHidden(['phone', 'email']);

        return response()->json(['data' => $profile]);
    }
}
