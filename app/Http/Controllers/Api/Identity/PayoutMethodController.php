<?php

namespace App\Http\Controllers\Api\Identity;

use App\Http\Controllers\Controller;
use App\Models\PayoutMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutMethodController extends Controller
{
    public function index(Request $request)
    {
        $profile = $request->user()->providerProfile;

        return response()->json([
            'data' => $profile
                ? $profile->payoutMethods()->orderByDesc('is_default')->orderByDesc('id')->get()
                : [],
        ]);
    }

    public function store(Request $request)
    {
        $profile = $this->profile($request);

        $validated = $this->validatePayload($request);

        $method = DB::transaction(function () use ($profile, $validated) {
            $makeDefault = ($validated['is_default'] ?? false) || $profile->payoutMethods()->count() === 0;

            if ($makeDefault) {
                $profile->payoutMethods()->update(['is_default' => false]);
            }

            return $profile->payoutMethods()->create([
                'method_type'    => $validated['method_type'],
                'bank_name'      => $validated['bank_name'] ?? null,
                'account_name'   => $validated['account_name'],
                'account_number' => $validated['account_number'],
                'is_default'     => $makeDefault,
            ]);
        });

        return response()->json(['message' => 'Payout method added.', 'data' => $method], 201);
    }

    public function update(Request $request, int $id)
    {
        $profile = $this->profile($request);
        $method = $profile->payoutMethods()->findOrFail($id);

        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($profile, $method, $validated) {
            if (($validated['is_default'] ?? false)) {
                $profile->payoutMethods()->where('id', '!=', $method->id)->update(['is_default' => false]);
            }

            $method->update([
                'method_type'    => $validated['method_type'],
                'bank_name'      => $validated['bank_name'] ?? null,
                'account_name'   => $validated['account_name'],
                'account_number' => $validated['account_number'],
                'is_default'     => $validated['is_default'] ?? $method->is_default,
            ]);
        });

        return response()->json(['message' => 'Payout method updated.', 'data' => $method->fresh()]);
    }

    public function setDefault(Request $request, int $id)
    {
        $profile = $this->profile($request);
        $method = $profile->payoutMethods()->findOrFail($id);

        DB::transaction(function () use ($profile, $method) {
            $profile->payoutMethods()->update(['is_default' => false]);
            $method->update(['is_default' => true]);
        });

        return response()->json(['message' => 'Default payout method set.', 'data' => $method->fresh()]);
    }

    public function destroy(Request $request, int $id)
    {
        $profile = $this->profile($request);
        $method = $profile->payoutMethods()->findOrFail($id);
        $wasDefault = $method->is_default;

        $method->delete();

        // Promote the newest remaining method to default.
        if ($wasDefault) {
            $next = $profile->payoutMethods()->orderByDesc('id')->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Payout method removed.']);
    }

    private function profile(Request $request)
    {
        $profile = $request->user()->providerProfile;
        abort_if(! $profile, 404, 'Provider profile not found.');

        return $profile;
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'method_type'    => 'required|in:bank_transfer,mobile_money,paypal',
            'bank_name'      => 'nullable|string|max:120',
            'account_name'   => 'required|string|max:150',
            'account_number' => 'required|string|max:60',
            'is_default'     => 'nullable|boolean',
        ]);
    }
}
