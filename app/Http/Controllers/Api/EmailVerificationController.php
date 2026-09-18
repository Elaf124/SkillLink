<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends Controller
{
    /** Seconds a user must wait between code requests. */
    private const RESEND_COOLDOWN = 60;

    /** Minutes a verification code stays valid. */
    private const CODE_TTL = 10;

    /**
     * Issue (or re-issue) a verification code for the authenticated user and
     * email it. POST /api/email/verification-code
     */
    public function send(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already verified.']);
        }

        $existing = VerificationCode::where('user_id', $user->id)
            ->where('type', 'email_verification')
            ->first();

        if ($existing && $existing->last_sent_at) {
            $elapsed = (int) $existing->last_sent_at->diffInSeconds(now(), absolute: true);
            $wait = self::RESEND_COOLDOWN - $elapsed;
            if ($wait > 0) {
                return response()->json([
                    'message'     => "Please wait {$wait}s before requesting another code.",
                    'retry_after' => $wait,
                ], 429);
            }
        }

        self::issue($user);

        return response()->json(['message' => 'A verification code has been sent to your email.']);
    }

    /**
     * Confirm a code and mark the email verified. POST /api/email/verify
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already verified.', 'user' => $user->load('role')]);
        }

        $record = VerificationCode::where('user_id', $user->id)
            ->where('type', 'email_verification')
            ->first();

        if (! $record || $record->isExpired() || ! Hash::check($request->code, $record->code)) {
            return response()->json([
                'message' => 'That code is invalid or has expired. Request a new one.',
            ], 422);
        }

        $user->markEmailAsVerified();
        $record->delete();

        return response()->json([
            'message' => 'Email verified.',
            'user'    => $user->fresh()->load(['role', 'providerProfile']),
        ]);
    }

    /**
     * Generate a fresh 6-digit code, persist its hash, and send the email.
     * Shared with registration.
     */
    public static function issue(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        VerificationCode::updateOrCreate(
            [
                'user_id' => $user->id,
                'type'    => 'email_verification',
            ],
            [
                'email'        => $user->email,
                'code'         => Hash::make($code),
                'expires_at'   => now()->addMinutes(self::CODE_TTL),
                'last_sent_at' => now(),
            ],
        );

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->first_name));
    }
}
