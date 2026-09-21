<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Get authenticated user profile.
     */
    public function show(Request $request)
    {
        $user = $request->user()->load(['role', 'providerProfile']);

        // Stable contract for the frontend: `role` and `provider_profile` are
        // always present on this payload. `provider_profile` is the serialised
        // hasOne relation (snake_case, Laravel default) and is null for
        // non-provider accounts; for providers it includes the profile `id`,
        // which the frontend `isProvider` check relies on.
        return response()->json($user);
    }

    /**
     * Update the authenticated user's profile fields.
     * Called by PATCH /api/user from the frontend account settings page.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'first_name'      => 'sometimes|string|max:255',
            'last_name'       => 'sometimes|string|max:255',
            'email'           => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'           => 'sometimes|nullable|string|max:20',
            'city'            => 'sometimes|nullable|string|max:100',
            'area'            => 'sometimes|nullable|string|max:100',
            'bio'             => 'sometimes|nullable|string',
            'languages'       => 'sometimes|nullable|array',
            'company_name'    => 'sometimes|nullable|string|max:255',
            'company_website' => 'sometimes|nullable|string|max:255',
            'profile_photo'   => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (! empty($data['profile_photo']) && str_starts_with($data['profile_photo'], 'data:image/')) {
            $savedUrl = $this->storeBase64Photo($data['profile_photo'], $user->profile_photo);
            if ($savedUrl) {
                $data['profile_photo'] = $savedUrl;
            } else {
                unset($data['profile_photo']);
            }
        }

        $user->update($data);

        $freshUser = $user->fresh()->load(['role', 'providerProfile']);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data'    => $freshUser,
            'user'    => $freshUser,
        ]);
    }

    /**
     * Delete the authenticated user's account permanently.
     * Called by DELETE /api/user.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Password is required to confirm account deletion.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'The password you entered is incorrect.',
                'errors'  => ['password' => ['The password you entered is incorrect.']],
            ], 422);
        }

        // Safety check 1: Active or unresolved bookings
        $hasActiveBookings = Booking::where(function ($q) use ($user) {
            $q->where('customer_id', $user->id);
            if ($user->providerProfile) {
                $q->orWhere('provider_id', $user->providerProfile->id);
            }
        })
        ->whereIn('status', ['pending', 'accepted', 'awaiting_confirmation', 'disputed'])
        ->exists();

        if ($hasActiveBookings) {
            return response()->json([
                'message' => 'You cannot delete your account while you have active, accepted, or disputed bookings. Please complete or resolve your commitments first.',
            ], 422);
        }

        // Safety check 2: Outstanding provider wallet balance
        if ($user->providerProfile && $user->providerProfile->wallet) {
            if ($user->providerProfile->wallet->balance > 0) {
                return response()->json([
                    'message' => 'You have an unsettled wallet balance of ETB ' . number_format($user->providerProfile->wallet->balance, 2) . '. Please withdraw your balance before deleting your account.',
                ], 422);
            }
        }

        // Revoke all authentication tokens
        $user->tokens()->delete();

        // Delete user account (cascades related profiles, services, etc.)
        $user->delete();

        return response()->json([
            'message' => 'Your account has been deleted successfully.',
        ]);
    }

    /**
     * Upload or update the authenticated user's profile photo.
     * Called by POST /api/user/photo.
     */
    public function uploadPhoto(Request $request)
    {
        $user = $request->user();
        $photoUrl = null;

        if ($request->hasFile('photo')) {
            $validator = Validator::make($request->all(), [
                'photo' => 'required|file|mimes:jpeg,jpg,png,webp,gif|max:10240', // Max 10MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed: ' . implode(' ', $validator->errors()->all()),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Remove old profile photo file from public storage if it was stored locally
            if ($user->profile_photo && str_contains($user->profile_photo, '/storage/avatars/')) {
                $oldPath = 'avatars/' . basename($user->profile_photo);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $path = $request->file('photo')->store('avatars', 'public');
            $photoUrl = Storage::url($path);
        } elseif ($request->filled('photo') || $request->filled('photo_base64') || $request->filled('image')) {
            $base64Data = $request->input('photo') ?? $request->input('photo_base64') ?? $request->input('image');
            $photoUrl = $this->storeBase64Photo($base64Data, $user->profile_photo);
            if (! $photoUrl) {
                return response()->json(['message' => 'Invalid image format or data.'], 422);
            }
        } else {
            return response()->json([
                'message' => 'No image provided. Please select an image file to upload.',
                'errors'  => ['photo' => ['No image provided.']],
            ], 422);
        }

        $user->update([
            'profile_photo' => $photoUrl,
        ]);

        return response()->json([
            'message'       => 'Profile photo updated successfully.',
            'profile_photo' => $photoUrl,
            'user'          => $user->fresh()->load(['role', 'providerProfile']),
        ]);
    }

    /**
     * Store a base64 encoded image string to public avatar storage.
     */
    protected function storeBase64Photo(string $base64Data, ?string $oldPhotoUrl = null): ?string
    {
        $ext = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $ext = strtolower($matches[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            return null;
        }

        // Clean up old avatar if exists
        if ($oldPhotoUrl && str_contains($oldPhotoUrl, '/storage/avatars/')) {
            $oldPath = 'avatars/' . basename($oldPhotoUrl);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $filename = 'avatars/' . Str::random(40) . '.' . $ext;
        Storage::disk('public')->put($filename, $decoded);

        return Storage::url($filename);
    }

    /**
     * Remove the authenticated user's profile photo.
     * Called by DELETE /api/user/photo.
     */
    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->profile_photo && str_contains($user->profile_photo, '/storage/avatars/')) {
            $oldPath = 'avatars/' . basename($user->profile_photo);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $user->update([
            'profile_photo' => null,
        ]);

        return response()->json([
            'message' => 'Profile photo removed successfully.',
            'user'    => $user->fresh()->load(['role', 'providerProfile']),
        ]);
    }
}
