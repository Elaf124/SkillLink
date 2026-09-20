<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class PasswordResetController extends Controller
{
    /** Seconds between reset-code requests for the same email. */
    private const RESEND_COOLDOWN = 60;

    /** Minutes a reset code stays valid. */
    private const CODE_TTL = 15;

    private const GENERIC = 'If that email is registered, a reset code has been sent.';

    /**
     * Email a reset code. POST /api/forgot-password
     * Always responds 200 with the same message so registered emails can't be
     * enumerated.
     */
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $email = strtolower(trim($request->email));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            return response()->json([
                'message' => 'No account found with this email address. Please check the spelling or sign up.',
            ], 422);
        }

        $recent = VerificationCode::where('email', $user->email)
            ->where('type', 'password_reset')
            ->where('created_at', '>', now()->subSeconds(self::RESEND_COOLDOWN))
            ->latest('id')
            ->first();

        if ($recent) {
            $elapsed = (int) $recent->created_at->diffInSeconds(now(), absolute: true);
            $wait = max(1, self::RESEND_COOLDOWN - $elapsed);
            return response()->json([
                'message'     => "Please wait {$wait}s before requesting another code.",
                'retry_after' => $wait,
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        VerificationCode::where('email', $user->email)
            ->where('type', 'password_reset')
            ->delete();

        VerificationCode::create([
            'user_id'      => $user->id,
            'email'        => $user->email,
            'code'         => Hash::make($code),
            'type'         => 'password_reset',
            'expires_at'   => now()->addMinutes(self::CODE_TTL),
            'last_sent_at' => now(),
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetCodeMail($code, $user->first_name));
            Log::info("Password reset code emailed to: {$user->email}");
        } catch (\Throwable $e) {
            Log::error("Password reset email failed to {$user->email}: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send reset email (' . $e->getMessage() . '). Please try again later.',
            ], 500);
        }

        return response()->json(['message' => 'A reset code has been sent to your email.']);
    }

    /**
     * Verify a reset code and set the new password. POST /api/reset-password
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'code'     => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $email = strtolower(trim($request->email));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        $record = $user
            ? VerificationCode::where('email', $user->email)
                ->where('type', 'password_reset')
                ->latest('id')
                ->first()
            : null;

        if (! $user || ! $record || $record->isExpired() || ! Hash::check($request->code, $record->code)) {
            return response()->json([
                'message' => 'That code is invalid or has expired. Request a new one.',
            ], 422);
        }

        $user->forceFill(['password' => Hash::make($request->password)])->save();

        // Invalidate the code and log the user out everywhere.
        VerificationCode::where('email', $user->email)
            ->where('type', 'password_reset')
            ->delete();
        $user->tokens()->delete();

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }
}
