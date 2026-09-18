<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\Payout;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    private const MIN_WITHDRAWAL = 50;

    /**
     * The provider's wallet snapshot: real balance, lifetime figures,
     * an escrow estimate, and recent ledger entries.
     */
    public function show(Request $request)
    {
        $profile = $this->profile($request);
        $wallet = Wallet::firstOrCreate(['provider_id' => $profile->id], ['balance' => 0]);

        $activeEscrow = Booking::where('provider_id', $profile->id)
            ->whereIn('status', ['accepted', 'in_progress', 'awaiting_confirmation'])
            ->sum('total_amount');

        return response()->json([
            'data' => [
                'balance'            => (float) $wallet->balance,
                'lifetime_credited'  => (float) $wallet->transactions()->where('type', 'credit')->sum('amount'),
                'lifetime_withdrawn' => (float) $profile->payouts()->where('status', 'completed')->sum('amount'),
                'pending_escrow'     => round($activeEscrow * 0.9, 2), // estimate, 90% provider share
                'min_withdrawal'     => self::MIN_WITHDRAWAL,
                'transactions'       => $wallet->transactions()
                    ->latest()
                    ->limit(25)
                    ->get(['id', 'booking_id', 'type', 'amount', 'description', 'created_at']),
            ],
        ]);
    }

    public function payouts(Request $request)
    {
        $profile = $this->profile($request);

        return response()->json([
            'data' => $profile->payouts()
                ->with('payoutMethod:id,method_type,bank_name')
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Withdraw from the wallet to a saved payout method.
     * Disbursement is still simulated, but every record is real:
     * wallet balance drops, a debit WalletTransaction and a Payout row are written.
     */
    public function withdraw(Request $request)
    {
        $profile = $this->profile($request);
        $wallet = Wallet::firstOrCreate(['provider_id' => $profile->id], ['balance' => 0]);

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:' . self::MIN_WITHDRAWAL,
            'payout_method_id' => 'nullable|integer',
        ]);

        $methodId = $validated['payout_method_id'] ?? null;
        $method = $methodId
            ? $profile->payoutMethods()->find($methodId)
            : ($profile->payoutMethods()->where('is_default', true)->first()
                ?? $profile->payoutMethods()->latest()->first());

        if (! $method) {
            return response()->json(['message' => 'Add a payout method before withdrawing.'], 422);
        }

        $amount = round((float) $validated['amount'], 2);

        if ($amount > (float) $wallet->balance) {
            return response()->json(['message' => 'Amount exceeds your available balance.'], 422);
        }

        $payout = DB::transaction(function () use ($wallet, $profile, $method, $amount) {
            $wallet->decrement('balance', $amount);

            WalletTransaction::create([
                'wallet_id'         => $wallet->id,
                'booking_id'        => null,
                'type'              => 'debit',
                'amount'            => $amount,
                'description'       => 'Withdrawal to ' . ($method->bank_name ?: $method->method_type),
                'gateway_simulated' => true,
            ]);

            return Payout::create([
                'provider_id'      => $profile->id,
                'payout_method_id' => $method->id,
                'amount'           => $amount,
                'reference'        => 'PO-' . strtoupper(uniqid()),
                'status'           => 'completed', // simulated instant settlement
                'destination'      => trim(($method->bank_name ?: ucfirst(str_replace('_', ' ', $method->method_type))) . ' ' . $method->account_number_masked),
                'processed_at'     => now(),
            ]);
        });

        Notification::notify(
            $profile->user_id,
            'payment',
            'Withdrawal sent',
            'ETB ' . number_format($amount) . " is on its way to {$payout->destination}.",
            '/provider/wallet',
        );

        return response()->json([
            'message' => 'Withdrawal processed.',
            'data'    => $payout->load('payoutMethod:id,method_type,bank_name'),
        ], 201);
    }

    private function profile(Request $request)
    {
        $profile = $request->user()->providerProfile;
        abort_if(! $profile, 404, 'Provider profile not found.');

        return $profile;
    }
}
