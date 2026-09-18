<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Blocks API actions until the account's email is verified.
     * Usage in routes: ->middleware('verified.email')
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message'        => 'Please verify your email address to continue.',
                'email_verified' => false,
            ], 403);
        }

        return $next($request);
    }
}
