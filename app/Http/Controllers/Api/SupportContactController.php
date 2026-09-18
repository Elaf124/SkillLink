<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SupportContactController extends Controller
{
    /**
     * Customer/provider "Contact Support & Report Issues" form (/support).
     * Stored as a UserReport with type=technical (no reported user — this is
     * a platform issue, not a complaint about another person) so it shows up
     * for admin_support under the "Technical Inquiries" tab.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:150',
            'message' => 'required|string|max:3000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $inquiry = UserReport::create([
            'reporter_id'       => $request->user()->id,
            'reported_user_id'  => null,
            'type'              => 'technical',
            'subject'           => $validator->validated()['subject'],
            'reason'            => $validator->validated()['message'],
            'status'            => 'pending',
        ]);

        return response()->json([
            'message' => 'Your message has been sent to our support team.',
            'data'    => $inquiry,
        ], 201);
    }
}
