<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class JobController extends Controller
{
    /**
     * Public browsing — providers see open jobs to submit offers on.
     */
    public function index(Request $request)
    {
        $query = Job::with(['customer', 'category'])
            ->where('status', 'open');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $jobs = $query->latest()->paginate(20);

        return response()->json($jobs);
    }

    /**
     * View a single job with its attachments and offers.
     */
    public function show(int $id)
    {
        $job = Job::with(['customer', 'category', 'attachments', 'offers.provider.user'])
            ->find($id);

        if (! $job) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        return response()->json(['data' => $job]);
    }

    /**
     * Customer posts a new job.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id'     => 'required|exists:categories,id',
            'title'           => 'required|string|max:150',
            'description'     => 'required|string',
            'budget'          => 'required|numeric|min:0',
            'location'        => 'nullable|string|max:150',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'preferred_date'  => 'nullable|date|after_or_equal:today',
            'attachments'     => 'nullable|array|max:5',
            'attachments.*'   => 'file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $job = Job::create(array_merge(collect($validator->validated())->except('attachments')->all(), [
            'customer_id' => $request->user()->id,
            'status'      => 'open',
        ]));

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('job-attachments', 'public');
            $job->attachments()->create(['file_url' => Storage::url($path)]);
        }

        return response()->json([
            'message' => 'Job posted successfully',
            'data'    => $job->load('attachments'),
        ], 201);
    }

    /**
     * Customer views their OWN posted jobs.
     */
    public function myJobs(Request $request)
    {
        $jobs = Job::with(['category', 'offers'])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($jobs);
    }

    /**
     * Customer updates their OWN job — only while still 'open'.
     */
    public function update(Request $request, int $id)
    {
        $job = Job::find($id);

        if (! $job) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        if ($job->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your job.'], 403);
        }

        if ($job->status !== 'open') {
            return response()->json(['message' => 'This job can no longer be edited.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'category_id'     => 'sometimes|exists:categories,id',
            'title'           => 'sometimes|string|max:150',
            'description'     => 'sometimes|string',
            'budget'          => 'sometimes|numeric|min:0',
            'location'        => 'nullable|string|max:150',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'preferred_date'  => 'nullable|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $job->update($validator->validated());

        return response()->json([
            'message' => 'Job updated successfully',
            'data'    => $job->fresh(),
        ]);
    }

    /**
     * Customer cancels their OWN job — only while still 'open'.
     */
    public function destroy(Request $request, int $id)
    {
        $job = Job::find($id);

        if (! $job) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        if ($job->customer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden. This is not your job.'], 403);
        }

        if ($job->status !== 'open') {
            return response()->json(['message' => 'This job can no longer be cancelled.'], 422);
        }

        $job->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Job cancelled successfully']);
    }
}
