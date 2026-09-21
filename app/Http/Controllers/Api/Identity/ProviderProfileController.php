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
            ->with([
                'user',
                'services',
                'skills',
                'portfolio',
                'availability',
                'verifications' => function ($q) {
                    $q->where('document_category', 'certification');
                },
            ])
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
            'bio'                 => 'sometimes|nullable|string',
            'professional_title'  => 'sometimes|string|max:150',
            'experience_years'    => 'sometimes|integer|min:0',
            'latitude'            => 'sometimes|numeric|between:-90,90',
            'longitude'           => 'sometimes|numeric|between:-180,180',
            'skills'              => 'sometimes|array',
            'skills.*.id'         => 'required_with:skills|integer|exists:skills,id',
            'skills.*.proficiency_level' => 'nullable|in:beginner,intermediate,expert',
            'experience'          => 'sometimes|nullable|array',
            'education'           => 'sometimes|nullable|array',
            'availability'        => 'sometimes|array',
            'availability.*.day_of_week' => 'required_with:availability|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'availability.*.start_time'  => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'availability.*.end_time'    => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'availability.*.is_available' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->input('availability', []) as $slot) {
            if (! empty($slot['start_time']) && ! empty($slot['end_time'])) {
                $start = substr($slot['start_time'], 0, 5);
                $end = substr($slot['end_time'], 0, 5);
                if ($end <= $start) {
                    return response()->json(['message' => 'Validation failed', 'errors' => ['availability' => ['Each availability end time must be after its start time.']]], 422);
                }
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

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('avatars', 'public');
            $request->user()->update(['profile_photo' => Storage::url($path)]);
        } elseif ($request->filled('profile_photo')) {
            $request->user()->update(['profile_photo' => $request->input('profile_photo')]);
        }

        return response()->json([
            'message' => 'Provider profile updated successfully',
            'data'    => $profile->fresh(['user', 'services', 'skills', 'portfolio', 'availability', 'verifications']),
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

    public function indexPublic(Request $request)
    {
        $query = ProviderProfile::with(['user', 'services' => function ($q) {
            $q->where('service_status', 'active')->with('category');
        }, 'skills'])
            ->where('verification_status', 'verified');

        if ($request->filled('category_id')) {
            $catId = $request->category_id;
            $catIds = \App\Models\Category::where('id', $catId)
                ->orWhere('parent_category_id', $catId)
                ->pluck('id');
            $catNames = \App\Models\Category::whereIn('id', $catIds)->pluck('name');

            $query->where(function ($q) use ($catIds, $catNames) {
                $q->whereHas('services', fn ($sq) => $sq->whereIn('category_id', $catIds));
                foreach ($catNames as $name) {
                    $q->orWhere('professional_title', 'like', "%{$name}%")
                      ->orWhere('bio', 'like', "%{$name}%");
                }
            });
        }

        if ($request->filled('q')) {
            $rawSearch = trim($request->q);
            $tokens = array_filter(explode(' ', strtolower($rawSearch)));

            foreach ($tokens as $token) {
                $query->where(function ($q) use ($token) {
                    $q->where('professional_title', 'like', "%{$token}%")
                      ->orWhere('bio', 'like', "%{$token}%")
                      ->orWhere('business_name', 'like', "%{$token}%")
                      ->orWhereHas('user', function ($uq) use ($token) {
                          $uq->where('first_name', 'like', "%{$token}%")
                             ->orWhere('last_name', 'like', "%{$token}%")
                             ->orWhere('city', 'like', "%{$token}%")
                             ->orWhere('area', 'like', "%{$token}%");
                      })
                      ->orWhereHas('services', function ($sq) use ($token) {
                          $sq->where('title', 'like', "%{$token}%")
                             ->orWhere('description', 'like', "%{$token}%");
                      })
                      ->orWhereHas('skills', function ($skq) use ($token) {
                          $skq->where('skill_name', 'like', "%{$token}%");
                      });
                });
            }
        }

        $providers = $query->paginate(24);

        $providers->getCollection()->each(function ($profile) {
            $profile->user?->makeHidden(['phone', 'email']);
        });

        return response()->json($providers);
    }

    public function showPublic(int $id)
    {
        $profile = ProviderProfile::with([
            'user',
            'services',
            'skills',
            'portfolio',
            'availability',
            'verifications' => function ($q) {
                $q->where('document_category', 'certification')
                  ->where('verification_status', 'approved');
            },
        ])
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

    public function onboard(Request $request)
    {
        $user = $request->user();
        $profile = $user->providerProfile;

        if (! $profile) {
            $profile = $user->providerProfile()->create([
                'verification_status' => 'pending',
            ]);
        }

        $type = $request->input('provider_type', 'individual');

        if ($type === 'individual') {
            $title = $request->input('title');
            $intro = $request->input('introduction');
            $phone = $request->input('phone');
            $city = $request->input('city');
            $address = $request->input('address');

            $experience = $request->input('experience');
            if (is_string($experience)) {
                $experience = json_decode($experience, true) ?: [];
            }
            $education = $request->input('education');
            if (is_string($education)) {
                $education = json_decode($education, true) ?: [];
            }

            $profileUpdates = [
                'professional_title' => $title ?: $profile->professional_title,
                'bio'                => $intro ?: $profile->bio,
            ];
            if ($experience !== null) {
                $profileUpdates['experience'] = $experience;
            }
            if ($education !== null) {
                $profileUpdates['education'] = $education;
            }

            $profile->update($profileUpdates);

            $userUpdates = array_filter([
                'phone' => $phone,
                'city'  => $city,
                'area'  => $address,
            ]);

            if ($request->hasFile('profile_picture')) {
                $path = $request->file('profile_picture')->store('avatars', 'public');
                $userUpdates['profile_photo'] = Storage::url($path);
            }

            if (! empty($userUpdates)) {
                $user->update($userUpdates);
            }

            if ($request->hasFile('identity_document')) {
                $path = $request->file('identity_document')->store('provider-verification', 'public');
                $profile->verifications()->create([
                    'document_category'   => 'verification',
                    'document_type'       => 'national_id',
                    'document_name'       => 'National ID / Passport',
                    'file_url'            => Storage::url($path),
                    'verification_status' => 'pending',
                ]);
            }
        } else {
            $name = $request->input('company_name');
            $website = $request->input('company_website');
            $desc = $request->input('company_description');

            $profile->update([
                'business_name'      => $name ?: $profile->business_name,
                'professional_title' => $name ?: $profile->professional_title,
                'bio'                => $desc ?: $profile->bio,
            ]);

            $userUpdates = array_filter([
                'company_name'    => $name,
                'company_website' => $website,
            ]);

            if ($request->hasFile('company_logo')) {
                $path = $request->file('company_logo')->store('avatars', 'public');
                $userUpdates['profile_photo'] = Storage::url($path);
            }

            if (! empty($userUpdates)) {
                $user->update($userUpdates);
            }

            if ($request->hasFile('business_license')) {
                $path = $request->file('business_license')->store('provider-verification', 'public');
                $profile->verifications()->create([
                    'document_category'   => 'verification',
                    'document_type'       => 'business_license',
                    'document_name'       => 'Business License',
                    'file_url'            => Storage::url($path),
                    'verification_status' => 'pending',
                ]);
            }
        }

        return response()->json([
            'message' => 'Onboarding application submitted successfully.',
            'data'    => $profile->fresh(['user', 'verifications']),
        ]);
    }
}

