<?php

namespace App\Repositories;

use App\Models\JobListing;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentTransaction;

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

    public function getJobBySlug($id)
    {
        return JobListing::active()
            ->with('postedBy:id,name')->where('slug', $id)->first();
        // ->findOrFail($id);
    }


    public function getRelatedJobs($job, $limit = 3)
    {
        return JobListing::active()
            ->where('id', '!=', $job->id)
            ->where('tier', 'free')
            ->where(function ($q) use ($job) {
                $q->where('type', $job->type)
                    ->orWhere('location', 'like', "%{$job->location}%");
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function incrementViews($job)
    {
        $job->incrementViews();
        return $job;
    }

    public function hasUserPurchasedJob($jobId, $userId)
    {
        return DB::table('job_user')
            ->where('job_listing_id', $jobId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function purchaseJobPoint($jobId, $userId, $amount, $currency, $job)
    {
        PaymentTransaction::create([
            'user_id'     => $userId,
            'campaign_id' => '1',
            'reference'   => time(),
            'amount'      => $amount,
            'balance'     => walletBalance()->balance,
            'status'      => 'successful',
            'currency'    => $currency->code,
            'channel'     => 'wallet',
            'type'        => 'job_point_purchase',
            'description' => $job->title . ' premium job point purchase',
            'tx_type'     => 'Debit',
            'user_type'   => 'regular',
        ]);

        DB::table('job_user')->insert([
            'job_listing_id' => $jobId,
            'user_id'        => $userId,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
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
