<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user->update($validator->validated());

        return response()->json([
            'data' => $user->fresh()->load('role'),
        ]);
    }
}
