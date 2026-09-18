<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\CustomerProfile;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    
    public function redirect()
    {
        return Socialite::driver('google')->stateless()-> redirect();
    }


    public function callback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $customerRole = Role::where('name', 'customer')->first();

            $nameParts = explode(' ', $googleUser->getName(), 2);

            $user = User::create([
                'role_id'           => $customerRole->id,
                'first_name'        => $nameParts[0] ?? 'Google',
                'last_name'         => $nameParts[1] ?? 'User',
                'email'             => $googleUser->getEmail(),
                'phone'             => null,
                'password'          => null,
                'google_id'         => $googleUser->getId(),
                'profile_photo'     => $googleUser->getAvatar(),
                'account_status'    => 'active',
                // Google has already confirmed ownership of this address.
                'email_verified_at' => now(),
            ]);

            CustomerProfile::create(['user_id' => $user->id]);
        } else {
            $fill = [];
            if (! $user->google_id) $fill['google_id'] = $googleUser->getId();
            if (! $user->email_verified_at) $fill['email_verified_at'] = now();
            if ($fill) $user->update($fill);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL'));

        return redirect("{$frontendUrl}/oauth/callback?token={$token}");
    }
}