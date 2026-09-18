<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\ProviderProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceDashboardController extends Controller
{
    /**
     * Which providers have money in their wallet, and whether they have a
     * payout destination on file. Lets finance see who can actually be paid.
     */
    public function payoutReadiness(Request $request)
    {
        $providers = ProviderProfile::query()
            ->with([
                'user:id,first_name,last_name,email',
                'wallet',
                'payoutMethods' => fn ($q) => $q->where('is_default', true),
            ])
            ->whereHas('wallet', fn ($q) => $q->where('balance', '>', 0))
            ->get()
            ->map(function (ProviderProfile $p) {
                $default = $p->payoutMethods->first();

                return [
                    'provider_id'        => $p->id,
                    'name'               => trim(($p->user->first_name ?? '') . ' ' . ($p->user->last_name ?? '')),
                    'email'              => $p->user->email ?? null,
                    'professional_title' => $p->professional_title,
                    'wallet_balance'     => (float) ($p->wallet->balance ?? 0),
                    'has_payout_method'  => (bool) $default,
                    'payout_method'      => $default ? [
                        'method_type'            => $default->method_type,
                        'bank_name'              => $default->bank_name,
                        'account_name'           => $default->account_name,
                        'account_number_masked'  => $default->account_number_masked,
                    ] : null,
                ];
            })
            ->sortByDesc('wallet_balance')
            ->values();

        return response()->json([
            'data'    => $providers,
            'summary' => [
                'ready'     => $providers->where('has_payout_method', true)->count(),
                'not_ready' => $providers->where('has_payout_method', false)->count(),
                'total_in_wallets' => round($providers->sum('wallet_balance'), 2),
            ],
        ]);
    }

    /**
     * High-level KPI stats for the finance dashboard.
     * Accessible only to users with role: admin_finance
     */
    public function stats(Request $request)
    {
        // Total platform revenue (sum of all successful payments)
        $totalRevenue = Payment::where('payment_status', 'paid')->sum('amount');

        // Platform commission earned
        $totalCommission = Payment::where('payment_status', 'paid')->sum('commission');

        // Total paid-out to providers
        $totalProviderPayout = Payment::where('payment_status', 'paid')->sum('provider_amount');

        // Number of paid transactions
        $paidTransactions = Payment::where('payment_status', 'paid')->count();

        // Average transaction value
        $avgTransaction = $paidTransactions > 0
            ? round($totalRevenue / $paidTransactions, 2)
            : 0;

        // Pending payments (bookings completed but not yet paid)
        $pendingPayments = Booking::where('status', 'completed')
            ->whereDoesntHave('payments', fn($q) => $q->where('payment_status', 'paid'))
            ->count();

        // Commission rate (the current platform rate)
        $commissionRate = 10; // 10% — matches PaymentController::COMMISSION_RATE

        // 30-day daily revenue chart data
        $dailyRevenue = Payment::where('payment_status', 'paid')
            ->where('paid_at', '>=', now()->subDays(29)->startOfDay())
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(amount) as total'),
                DB::raw('SUM(commission) as commission')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill in all 30 days (even days with no revenue)
        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $chartData[] = [
                'date'       => $day,
                'label'      => now()->subDays($i)->format('M j'),
                'total'      => isset($dailyRevenue[$day]) ? (float) $dailyRevenue[$day]->total : 0,
                'commission' => isset($dailyRevenue[$day]) ? (float) $dailyRevenue[$day]->commission : 0,
            ];
        }

        // Revenue by payment method breakdown
        $byMethod = Payment::where('payment_status', 'paid')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn($r) => [
                'method' => $r->payment_method,
                'count'  => $r->count,
                'total'  => (float) $r->total,
            ]);

        return response()->json([
            'data' => [
                'kpis' => [
                    'total_revenue'        => (float) $totalRevenue,
                    'total_commission'     => (float) $totalCommission,
                    'total_provider_payout'=> (float) $totalProviderPayout,
                    'paid_transactions'    => $paidTransactions,
                    'avg_transaction'      => (float) $avgTransaction,
                    'pending_payments'     => $pendingPayments,
                    'commission_rate'      => $commissionRate,
                ],
                'daily_revenue' => $chartData,
                'by_method'     => $byMethod,
            ],
        ]);
    }

    /**
     * Paginated list of all payments with full context.
     */
    public function transactions(Request $request)
    {
        $query = Payment::with([
            'booking.customer:id,first_name,last_name,email',
            'booking.provider.user:id,first_name,last_name,email',
        ])
        ->orderByDesc('paid_at');

        // Optional filters
        if ($request->filled('status')) {
            $query->where('payment_status', $request->status);
        }
        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }
        if ($request->filled('from')) {
            $query->where('paid_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('paid_at', '<=', $request->to);
        }

        $payments = $query->paginate(20);

        $payments->load('milestone:id,title');

        $payments->getCollection()->transform(function ($payment) {
            $customer = $payment->booking?->customer;
            $provider = $payment->booking?->provider?->user;

            return [
                'id'                    => $payment->id,
                'booking_id'            => $payment->booking_id,
                'milestone_id'          => $payment->milestone_id,
                'milestone_title'       => $payment->milestone?->title,
                'amount'                => (float) $payment->amount,
                'commission'            => (float) $payment->commission,
                'provider_amount'       => (float) $payment->provider_amount,
                'payment_method'        => $payment->payment_method,
                'payment_status'        => $payment->payment_status,
                'transaction_reference' => $payment->transaction_reference,
                'paid_at'               => $payment->paid_at,
                'created_at'            => $payment->created_at,
                'customer' => $customer ? [
                    'id'    => $customer->id,
                    'name'  => trim("{$customer->first_name} {$customer->last_name}"),
                    'email' => $customer->email,
                ] : null,
                'provider' => $provider ? [
                    'id'    => $provider->id,
                    'name'  => trim("{$provider->first_name} {$provider->last_name}"),
                    'email' => $provider->email,
                ] : null,
            ];
        });

        return response()->json(['data' => $payments]);
    }
}
