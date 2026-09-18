<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\ProviderProfile;
use App\Models\ProviderVerification;
use Illuminate\Http\Request;

class ProviderVerificationController extends Controller
{
    /**
     * Review queue: provider profiles with their verification documents,
     * newest applicants first. Filterable by profile status.
     */
    public function index(Request $request)
    {
        $query = ProviderProfile::query()
            ->with([
                'user:id,first_name,last_name,email,phone,city',
                'verifications' => fn ($q) => $q->latest(),
                'skills:id,skill_name',
            ])
            ->withCount(['verifications as pending_documents_count' => fn ($q) => $q->where('verification_status', 'pending')]);

        if ($request->filled('status')) {
            $query->where('verification_status', $request->status);
        }

        $providers = $query->orderByRaw("FIELD(verification_status, 'pending', 'suspended', 'verified')")
            ->latest()
            ->get()
            ->map(fn (ProviderProfile $p) => [
                'id'                  => $p->id,
                'user_id'             => $p->user_id,
                'name'                => trim(($p->user->first_name ?? '') . ' ' . ($p->user->last_name ?? '')),
                'email'               => $p->user->email ?? null,
                'phone'               => $p->user->phone ?? null,
                'city'                => $p->user->city ?? null,
                'professional_title'  => $p->professional_title,
                'bio'                 => $p->bio,
                'experience_years'    => $p->experience_years,
                'skills'              => $p->skills->pluck('skill_name'),
                'verification_status' => $p->verification_status,
                'pending_documents'   => $p->pending_documents_count,
                'applied_at'          => $p->created_at?->toISOString(),
                'documents'           => $p->verifications->map(fn (ProviderVerification $d) => [
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
                ]),
            ]);

        return response()->json([
            'data'    => $providers,
            'summary' => [
                'pending'   => ProviderProfile::where('verification_status', 'pending')->count(),
                'verified'  => ProviderProfile::where('verification_status', 'verified')->count(),
                'suspended' => ProviderProfile::where('verification_status', 'suspended')->count(),
            ],
        ]);
    }

    /**
     * Approve / reject a single document.
     */
    public function reviewDocument(Request $request, int $id)
    {
        $validated = $request->validate(['status' => 'required|in:approved,rejected']);

        $doc = ProviderVerification::findOrFail($id);
        $doc->update([
            'verification_status' => $validated['status'],
            'reviewed_by'         => $request->user()->id,
            'reviewed_at'         => now(),
        ]);

        return response()->json(['message' => "Document {$validated['status']}.", 'data' => $doc->fresh()]);
    }

    /**
     * The actual gate: set the provider profile's verification status.
     * This is what makes a provider publicly bookable (or not).
     */
    public function decideProvider(Request $request, int $providerId)
    {
        $validated = $request->validate([
            'status' => 'required|in:verified,rejected,suspended',
            'note'   => 'nullable|string|max:500',
        ]);
        $note = $validated['note'] ?? null;

        $profile = ProviderProfile::with('user')->findOrFail($providerId);

        // "rejected" is not a provider_profiles state — it maps back to pending
        // (the application was turned down; they can resubmit).
        $newStatus = $validated['status'] === 'rejected' ? 'pending' : $validated['status'];
        $profile->update(['verification_status' => $newStatus]);

        // Reflect the decision on any still-pending documents.
        if ($validated['status'] === 'verified') {
            $profile->verifications()->where('verification_status', 'pending')
                ->update(['verification_status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        } elseif ($validated['status'] === 'rejected') {
            $profile->verifications()->where('verification_status', 'pending')
                ->update(['verification_status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        }

        $messages = [
            'verified'  => 'Your provider account has been verified — you can now accept bookings.',
            'rejected'  => 'Your verification was not approved' . ($note ? ': ' . $note : '. Please resubmit your documents.'),
            'suspended' => 'Your provider account has been suspended' . ($note ? ': ' . $note : '. Contact support.'),
        ];

        Notification::notify(
            $profile->user_id,
            'verification',
            'Verification ' . $validated['status'],
            $messages[$validated['status']],
            '/account',
        );

        return response()->json([
            'message' => 'Provider verification updated.',
            'data'    => ['verification_status' => $profile->verification_status],
        ]);
    }
}
