<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * List the current customer's favorite providers.
     * Each entry is shaped like the public provider payload so the
     * frontend can render it without a second lookup.
     */
    public function index(Request $request)
    {
        $favorites = Favorite::where('customer_id', $request->user()->id)
            ->with([
                'provider.user',
                'provider.services.category',
            ])
            ->latest()
            ->get();

        $data = $favorites
            ->map(function (Favorite $favorite) {
                $provider = $favorite->provider;
                if (! $provider) {
                    return null;
                }

                return array_merge($provider->toArray(), [
                    'favorited_at' => $favorite->created_at,
                ]);
            })
            ->filter()
            ->values();

        return response()->json(['data' => $data]);
    }

    /**
     * Add a provider to the current customer's favorites. Idempotent.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider_id' => 'required|integer|exists:provider_profiles,id',
        ]);

        $favorite = Favorite::firstOrCreate([
            'customer_id' => $request->user()->id,
            'provider_id' => $validated['provider_id'],
        ]);

        return response()->json([
            'message' => 'Provider added to favorites.',
            'data'    => ['provider_id' => $favorite->provider_id],
        ], $favorite->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove a provider from the current customer's favorites. Idempotent.
     */
    public function destroy(Request $request, int $providerId)
    {
        Favorite::where('customer_id', $request->user()->id)
            ->where('provider_id', $providerId)
            ->delete();

        return response()->json(['message' => 'Provider removed from favorites.']);
    }
}
