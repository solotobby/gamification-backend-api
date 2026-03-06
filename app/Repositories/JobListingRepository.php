<?php

namespace App\Repositories;

use App\Models\JobListing;

class JobListingRepository
{
    public function getJobListings(array $filters = [], $page = null)
    {
        return JobListing::active()
            ->filter($filters)
            ->with('postedBy:id,name')
            ->latest()
            ->paginate(12, ['*'], 'page', $page);
    }

    public function getJobById($id)
    {
        return JobListing::active()
            ->with('postedBy:id,name')
            ->findOrFail($id);
    }

    public function getRelatedJobs($job, $limit = 3)
    {
        return JobListing::active()
            ->where('id', '!=', $job->id)
            // ->where('tier', 'free')
            ->where('type', $job->type)
            // ->where(function ($q) use ($job) {
            //     $q->where('type', $job->type)
            //         // ->orWhere('location', 'like', "%{$job->location}%");
            // })
            ->limit($limit)
            ->get();
    }

    public function incrementViews($job)
    {
        $job->incrementViews();
        return $job;
    }

    public function checkHasApplied($job, $user)
    {
        return $job->hasApplied($user);
    }

    public function createApplication($job, $data)
    {
        $application = $job->applications()->create($data);
        $job->increment('applications_count');
        return $application;
    }
}
