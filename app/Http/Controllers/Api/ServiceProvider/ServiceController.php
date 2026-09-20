<?php

namespace App\Http\Controllers\Api\ServiceProvider;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    /**
     * Public browsing — list active services, optionally filtered by category.
     */
   public function index(Request $request)
{
    $query = Service::with(['provider.user', 'category'])
        ->where('service_status', 'active')
        ->whereHas('provider', function ($q) {
            $q->where('verification_status', 'verified');
        });

    if ($request->filled('category_id')) {
        $catIds = \App\Models\Category::where('id', $request->category_id)
            ->orWhere('parent_category_id', $request->category_id)
            ->pluck('id');
        $query->whereIn('category_id', $catIds);
    }

    $services = $query->paginate(20);

    return response()->json($services);
}

    /**
     * Public view of a single service.
     */
   public function show(int $id)
{
    $service = Service::with(['provider.user', 'category'])
        ->whereHas('provider', function ($q) {
            $q->where('verification_status', 'verified');
        })
        ->find($id);

    if (! $service) {
        return response()->json(['message' => 'Service not found.'], 404);
    }

    return response()->json(['data' => $service]);
}

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id'    => 'required|exists:categories,id',
            'title'          => 'required|string|max:150',
            'description'    => 'required|string',
            'price'          => 'required|numeric|min:0',
            'price_type'     => ['required', Rule::in(['fixed', 'hourly'])],
            'duration'       => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $providerProfile = $request->user()->providerProfile;

        $service = Service::create(array_merge($validator->validated(), [
            'provider_id'    => $providerProfile->id,
            'service_status' => 'active',
        ]));

        return response()->json([
            'message' => 'Service created successfully',
            'data'    => $service,
        ], 201);
    }

    /**
     * Provider updates their OWN service only.
     */
    public function update(Request $request, int $id)
    {
        $service = Service::find($id);

        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        // Ownership check — this is the important part
        if ($service->provider_id !== $request->user()->providerProfile->id) {
            return response()->json(['message' => 'Forbidden. This is not your service.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id'    => 'sometimes|exists:categories,id',
            'title'          => 'sometimes|string|max:150',
            'description'    => 'sometimes|string',
            'price'          => 'sometimes|numeric|min:0',
            'price_type'     => ['sometimes', Rule::in(['fixed', 'hourly'])],
            'duration'       => 'nullable|integer|min:1',
            'service_status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $service->update($validator->validated());

        return response()->json([
            'message' => 'Service updated successfully',
            'data'    => $service->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $service = Service::find($id);

        if (! $service) {
            return response()->json(['message' => 'Service not found.'], 404);
        }

        if ($service->provider_id !== $request->user()->providerProfile->id) {
            return response()->json(['message' => 'Forbidden. This is not your service.'], 403);
        }

        $service->delete();

        return response()->json(['message' => 'Service deleted successfully']);
    }
}