<?php

namespace App\Http\Controllers\Api\Identity;

use App\Http\Controllers\Controller;
use App\Models\ProviderVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderVerificationController extends Controller
{
    /**
     * The provider's own verification status + the documents they've uploaded.
     */
    public function index(Request $request)
    {
        $profile = $this->profile($request);

        return response()->json([
            'data' => [
                'verification_status' => $profile->verification_status,
                'documents' => $profile->verifications()
                    ->latest()
                    ->get()
                    ->map(fn (ProviderVerification $d) => $this->present($d, $request)),
            ],
        ]);
    }

    /**
     * Upload one identity / certification document.
     */
    public function store(Request $request)
    {
        $profile = $this->profile($request);

        $validated = $request->validate([
            'document_category'    => 'required|in:verification,certification',
            'document_type'        => 'required|string|max:80',
            'document_name'        => 'required|string|max:150',
            'file'                 => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'issuing_organization' => 'nullable|string|max:150',
            'issue_date'           => 'nullable|date',
            'credential_url'       => 'nullable|url|max:255',
        ]);

        $path = $request->file('file')->store('provider-verification', 'public');

        $doc = $profile->verifications()->create([
            'document_category'    => $validated['document_category'],
            'document_type'        => $validated['document_type'],
            'document_name'        => $validated['document_name'],
            'file_url'             => Storage::url($path),
            'issuing_organization' => $validated['issuing_organization'] ?? null,
            'issue_date'           => $validated['issue_date'] ?? null,
            'credential_url'       => $validated['credential_url'] ?? null,
            'verification_status'  => 'pending',
        ]);

        // A fresh submission moves the provider back into the review queue.
        if ($profile->verification_status === 'suspended') {
            $profile->update(['verification_status' => 'pending']);
        }

        return response()->json(['message' => 'Document uploaded for review.', 'data' => $this->present($doc, $request)], 201);
    }

    /**
     * Remove a document that has not been reviewed yet.
     */
    public function destroy(Request $request, int $id)
    {
        $profile = $this->profile($request);
        $doc = $profile->verifications()->findOrFail($id);

        if ($doc->verification_status !== 'pending') {
            return response()->json(['message' => 'Reviewed documents cannot be removed.'], 422);
        }

        if ($doc->file_url) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $doc->file_url));
        }
        $doc->delete();

        return response()->json(['message' => 'Document removed.']);
    }

    private function profile(Request $request)
    {
        $profile = $request->user()->providerProfile;
        abort_if(! $profile, 404, 'Provider profile not found.');

        return $profile;
    }

    private function present(ProviderVerification $d, Request $request): array
    {
        return [
            'id'                   => $d->id,
            'document_category'    => $d->document_category,
            'document_type'        => $d->document_type,
            'document_name'        => $d->document_name,
            'file_url'             => $d->file_url ? $request->getSchemeAndHttpHost() . $d->file_url : null,
            'issuing_organization' => $d->issuing_organization,
            'issue_date'           => $d->issue_date?->toDateString(),
            'credential_url'       => $d->credential_url,
            'verification_status'  => $d->verification_status,
            'reviewed_at'          => $d->reviewed_at?->toISOString(),
            'created_at'           => $d->created_at?->toISOString(),
        ];
    }
}
