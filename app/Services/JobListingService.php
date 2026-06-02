<?php

namespace App\Services;

use App\Repositories\JobListingRepository;
use Throwable;

class JobListingService
{
    protected $jobRepository;

    public function __construct(JobListingRepository $jobRepository)
    {
        $this->jobRepository = $jobRepository;
    }

    public function getJobListings($request)
    {
        try {
            $filters = $request->only([
                'type',
                'tier',
                'location',
                'salary_min',
                'salary_max',
                'remote',
                'search',
            ]);

            $jobs = $this->jobRepository->getJobListings($filters);

            $data = [];
            foreach ($jobs as $job) {
                $data[] = $this->formatJobSummary($job);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'Job listings retrieved successfully.',
                'data'       => $data,
                'pagination' => $this->buildPagination($jobs),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving job listings',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getJob($id)
    {
        try {
            $user = auth()->user();
            $job  = $this->jobRepository->getJobById($id);

            // Check premium access
            if (!$job->canView($user)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Please verify your email to access premium opportunities.',
                ], 403);
            }

            $this->jobRepository->incrementViews($job);

            $relatedJobs = $this->jobRepository->getRelatedJobs($job);

            $relatedData = [];
            foreach ($relatedJobs as $related) {
                $relatedData[] = $this->formatJobSummary($related);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Job retrieved successfully.',
                'data'    => array_merge(
                    $this->formatJob($job),
                    ['related_jobs' => $relatedData]
                ),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Job not found',
            ], 404);
        }
    }

    public function applyForJob($request, $id)
    {
        try {
            $user = auth()->user();
            $job  = $this->jobRepository->getJobById($id);

            // if (!$user->hasVerifiedEmail()) {
            //     return response()->json([
            //         'status'  => false,
            //         'message' => 'Please verify your email to apply.',
            //     ], 403);
            // }

            if ($this->jobRepository->checkHasApplied($job, $user)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have already applied to this position.',
                ], 409);
            }

            $validated = $request->validate([
                'cover_letter' => 'nullable|string|max:5000',
                'resume'       => 'nullable|file|mimes:pdf,doc,docx|max:5120',
            ]);

            $resumePath = null;
            // if ($request->hasFile('resume')) {
            //     $resumePath = uploadFileToCloudinary(
            //         $request->file('resume'),
            //         'files'
            //     );
            // }

            $application = $this->jobRepository->createApplication($job, [
                'user_id'      => $user->id,
                'cover_letter' => $validated['cover_letter'] ?? null,
                'resume_path'  => $resumePath,
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Application submitted successfully.',
                'data'    => $application,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error submitting application',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function jobDetails($id)
    {
        try {
            // $user = auth()->user();
            $job  = $this->jobRepository->getJobById($id);

            // Check premium access
            // if (!$job->canView($user)) {
            //     return response()->json([
            //         'status'  => false,
            //         'message' => 'Please verify your email to access premium opportunities.',
            //     ], 403);
            // }

            $this->jobRepository->incrementViews($job);

            $relatedJobs = $this->jobRepository->getRelatedJobs($job);

            $relatedData = [];
            foreach ($relatedJobs as $related) {
                $relatedData[] = $this->formatJobSummary($related);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Job retrieved successfully.',
                'data'    => array_merge(
                    $this->formatJob($job),
                    ['related_jobs' => $relatedData]
                ),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Job not found',
            ], 404);
        }
    }
    public function publicJobs($request)
    {
        try {
            $filters = $request->only([
                'type',
                'tier',
                'location',
                'salary_min',
                'salary_max',
                'remote',
                'search',
            ]);

            $jobs = $this->jobRepository->getJobListings($filters);

            $data = [];
            foreach ($jobs as $job) {
                $data[] = $this->formatJobSummary($job);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'Job listings retrieved successfully.',
                'data'       => $data,
                'pagination' => $this->buildPagination($jobs),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving job listings',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function formatJob($job)
    {
        return [
            'id'                  => $job->id,
            'title'               => $job->title,
            'slug'                => $job->slug,
            'description'         => $job->description,
            'requirements'        => $job->requirements,
            'responsibilities'    => $job->responsibilities,
            'benefits'            => $job->benefits,
            'type'                => $job->type,
            'tier'                => $job->tier,
            'location'            => $job->location,
            'remote_allowed'      => $job->remote_allowed,
            'salary_min'          => $job->salary_min,
            'salary_max'          => $job->salary_max,
            'salary_range'        => $job->salary_range,
            'currency'            => $job->currency,
            'company_name'        => $job->company_name,
            'company_logo'        => $job->company_logo,
            'company_description' => $job->company_description,
            'company_website'     => $job->company_website,
            'views_count'         => $job->views_count,
            'applications_count'  => $job->applications_count,
            'posted_by'           => $job->postedBy ? [
                'id'   => $job->postedBy->id,
                'name' => $job->postedBy->name,
            ] : null,
            'expires_at'          => $job->expires_at,
            'created_at'          => $job->created_at,
            'updated_at'          => $job->updated_at,
        ];
    }

    private function formatJobSummary($job)
    {
        $user = auth()->user();
        $check = true;

        if ($job->tier === 'premium') {

            if (!$user) {
                $check = 'Login required to view premium job.';
            } elseif (!$user->is_verified) {
                $check = 'Your account needs verification to view Job.';
            }
        }

        return [
            'id'                      => $job->id,
            'title'                   => $job->title,
            'slug'                    => $job->slug,
            'type'                    => $job->type,
            'tier'                    => $job->tier,
            'location'                => $job->location,
            'remote_allowed'          => $job->remote_allowed,
            'salary_range'            => $job->salary_range,
            'currency'                => $job->currency,
            'company_name'            => $job->company_name,
            'company_logo'            => $job->company_logo,
            'can_perform_task'        => $check === true,
            'can_perform_task_reason' => $check === true ? '' : $check,
            'created_at'              => $job->created_at,
        ];
    }

    private function buildPagination($paginator)
    {
        return [
            'total'        => $paginator->total(),
            'per_page'     => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page'    => $paginator->lastPage(),
            'from'         => $paginator->firstItem(),
            'to'           => $paginator->lastItem(),
        ];
    }
}
