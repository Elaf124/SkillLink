<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Notification;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupportDashboardController extends Controller
{
    /**
     * KPI stats for the support operations dashboard.
     * Accessible to users with role: admin_support (and super admin if needed).
     */
    public function stats(Request $request)
    {
        $openDisputes = Dispute::whereIn('status', ['open', 'under_review'])->count();
        $resolvedDisputes = Dispute::where('status', 'resolved')->count();
        $pendingReports = UserReport::userReports()->where('status', 'pending')->count();
        $totalReports = UserReport::userReports()->count();
        $openTechnicalInquiries = UserReport::technicalInquiries()->where('status', '!=', 'resolved')->count();

        $cancelledBookings = Booking::whereIn('status', ['cancelled_by_customer', 'cancelled_by_provider'])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        $disputedBookings = Booking::where('status', 'disputed')->count();

        // Recent disputes
        $recentDisputes = Dispute::with([
            'raisedBy:id,first_name,last_name,email',
            'booking:id,total_amount,status',
        ])
        ->latest()
        ->take(5)
        ->get();

        // Recent reports (community reports about another user, not technical inquiries)
        $recentReports = UserReport::userReports()
            ->with([
                'reporter:id,first_name,last_name,email',
                'reportedUser:id,first_name,last_name,email',
            ])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'data' => [
                'kpis' => [
                    'open_disputes'             => $openDisputes,
                    'resolved_disputes'         => $resolvedDisputes,
                    'pending_reports'           => $pendingReports,
                    'total_reports'             => $totalReports,
                    'open_technical_inquiries'  => $openTechnicalInquiries,
                    'cancelled_bookings'        => $cancelledBookings,
                    'disputed_bookings'         => $disputedBookings,
                ],
                'recent_disputes' => $recentDisputes,
                'recent_reports'  => $recentReports,
            ],
        ]);
    }

    /**
     * Paginated list of disputes.
     */
    public function disputes(Request $request)
    {
        $query = Dispute::with([
            'raisedBy:id,first_name,last_name,email',
            'resolvedBy:id,first_name,last_name,email',
            'booking.customer:id,first_name,last_name,email',
            'booking.provider.user:id,first_name,last_name,email',
            'booking.job:id,title',
            'booking.service:id,title',
        ])
        ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $disputes = $query->paginate(15);

        return response()->json(['data' => $disputes]);
    }

    /**
     * Update/Resolve a dispute.
     */
    public function updateDispute(Request $request, int $id)
    {
        $dispute = Dispute::find($id);
        if (! $dispute) {
            return response()->json(['message' => 'Dispute not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status'     => ['required', Rule::in(['open', 'under_review', 'resolved'])],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $dispute->status = $request->status;
        if ($request->has('resolution')) {
            $dispute->resolution = $request->resolution;
        }

        if ($request->status === 'resolved') {
            $dispute->resolved_by = $request->user()->id;
        }

        $dispute->save();

        if (in_array($request->status, ['under_review', 'resolved'], true)) {
            Notification::notify(
                $dispute->raised_by,
                'dispute',
                $request->status === 'resolved' ? 'Dispute resolved' : 'Dispute under review',
                $request->status === 'resolved'
                    ? 'Support resolved your dispute' . ($dispute->resolution ? ': ' . $dispute->resolution : '.')
                    : 'A support agent is now reviewing your dispute.',
                $dispute->booking_id ? '/bookings/' . $dispute->booking_id : '/support',
            );
        }

        return response()->json([
            'message' => 'Dispute updated successfully.',
            'data'    => $dispute->fresh(['raisedBy', 'resolvedBy', 'booking']),
        ]);
    }

    /**
     * Paginated list of user reports.
     */
    public function reports(Request $request)
    {
        // 'user' (default) = community reports about another user, shown under
        // the "User Reports" tab. 'technical' = the /support contact form,
        // shown under "Technical Inquiries" — same table, different lane.
        $type = $request->get('type', 'user') === 'technical' ? 'technical' : 'user';

        $query = UserReport::with([
            'reporter:id,first_name,last_name,email',
            'reportedUser:id,first_name,last_name,email',
        ])
        ->where('type', $type)
        ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(15);

        return response()->json(['data' => $reports]);
    }

    /**
     * Update status of a user report or technical inquiry.
     * 'resolved' only applies to technical inquiries; 'reviewed'/'dismissed'
     * are the outcomes for community reports about another user.
     */
    public function updateReport(Request $request, int $id)
    {
        $report = UserReport::find($id);
        if (! $report) {
            return response()->json(['message' => 'User report not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in(['pending', 'reviewed', 'dismissed', 'resolved'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $report->status = $request->status;
        $report->save();

        return response()->json([
            'message' => 'Report status updated successfully.',
            'data'    => $report->fresh(['reporter', 'reportedUser']),
        ]);
    }
}
