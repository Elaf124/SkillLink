<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Platform commission rate — kept as a constant for now.
     * Could later move to a settings table if you want it admin-configurable.
     */
    private const COMMISSION_RATE = 0.10; // 10%

    /**
     * Customer pays for a completed booking.
     * Simulated: no real gateway, we just record it and move money
     * from "customer" into the provider's wallet, minus commission.
     */
    public function pay(Request $request, int $bookingId)
    {
        $booking = Booking::find($bookingId);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        if ($booking->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your booking.'], 403);
        }

        if ($booking->status !== 'completed') {
            return response()->json(['message' => 'Payment can only be made after the booking is completed.'], 422);
        }

        $alreadyPaid = Payment::where('booking_id', $booking->id)
            ->where('payment_status', 'paid')
            ->exists();

        if ($alreadyPaid) {
            return response()->json(['message' => 'This booking has already been paid.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => ['required', Rule::in(['chapa_sim', 'telebirr_sim', 'cbe_birr_sim', 'cash_sim'])],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payment = DB::transaction(function () use ($booking, $request) {
            $amount = $booking->total_amount;
            $commission = round($amount * self::COMMISSION_RATE, 2);
            $providerAmount = $amount - $commission;

            $payment = Payment::create([
                'booking_id'             => $booking->id,
                'amount'                 => $amount,
                'commission'             => $commission,
                'provider_amount'        => $providerAmount,
                'payment_method'         => $request->payment_method,
                'payment_status'         => 'paid',
                'transaction_reference'  => 'TXN-' . strtoupper(uniqid()),
                'paid_at'                => now(),
            ]);

            // Get or create the provider's wallet
            $wallet = Wallet::firstOrCreate(
                ['provider_id' => $booking->provider_id],
                ['balance' => 0]
            );

            $wallet->increment('balance', $providerAmount);

            WalletTransaction::create([
                'wallet_id'          => $wallet->id,
                'booking_id'         => $booking->id,
                'type'               => 'credit',
                'amount'             => $providerAmount,
                'description'        => "Payment received for booking #{$booking->id}",
                'gateway_simulated'  => true,
            ]);

            return $payment;
        });

        $booking->loadMissing('provider:id,user_id');
        Notification::notify(
            $booking->provider?->user_id,
            'payment',
            'Payment received',
            'ETB ' . number_format((float) $payment->provider_amount) . " landed in your wallet for booking #{$booking->id}.",
            '/provider/wallet',
        );

        return response()->json([
            'message' => 'Payment processed successfully (simulated).',
            'data'    => $payment,
        ], 201);
    }

    /**
     * View payment details for a specific booking (either party can view).
     */
    public function show(Request $request, int $bookingId)
    {
        $booking = Booking::find($bookingId);

        if (! $booking) {
            return response()->json(['message' => 'Booking not found.'], 404);
        }

        $user = $request->user();
        $isCustomer = $booking->customer_id === $user->id;
        $isProvider = $user->providerProfile && $booking->provider_id === $user->providerProfile->id;

        if (! $isCustomer && ! $isProvider) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payments = Payment::where('booking_id', $bookingId)->get();

        return response()->json(['data' => $payments]);
    }
}