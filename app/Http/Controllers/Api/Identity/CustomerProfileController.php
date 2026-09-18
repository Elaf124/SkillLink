<?php

namespace App\Http\Controllers\Api\Identity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerProfileController extends Controller
{
    /**
     * View the logged-in customer's own profile.
     */
    public function show(Request $request)
    {
        $profile = $request->user()->customerProfile()
            ->with(['user'])
            ->first();

        if (! $profile) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        return response()->json(['data' => $profile]);
    }

    /**
     * Update the logged-in customer's own profile.
     */
    public function update(Request $request)
    {
        $profile = $request->user()->customerProfile;

        if (! $profile) {
            return response()->json(['message' => 'Customer profile not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'preferred_language' => 'sometimes|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $profile->update($validator->validated());

        return response()->json([
            'message' => 'Customer profile updated successfully',
            'data'    => $profile->fresh(),
        ]);
    }
}