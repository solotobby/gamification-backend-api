<?php

namespace App\Repositories;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Wallet;
use App\Models\PaymentTransaction;

class CampaignRepositoryModel
{
    public function listCategories()
    {
        return Category::orderBy(
            'name',
            'ASC'
        )->get();
    }

    public function listSubCategories($data)
    {
        return SubCategory::where(
            'category_id',
            $data
        )->orderBy(
            'name',
            'DESC'
        )->get();
    }


    public function getSubCategoryAmount($subcategoryId, $categoryId)
    {
        return SubCategory::where('id', $subcategoryId)
            ->where('category_id', $categoryId)
            ->select('amount')
            ->first();
    }
    public function createCampaign($request)
    {
        return Campaign::create($request->all());
    }

    // public function getCampaignsByPagination($id, $type, $per_page, $page = null)
    // {
    //     $query = Campaign::with(['campaignType'])->where(
    //         'user_id',
    //         $id
    //     );
    //     if (!empty($type)) {
    //         switch (strtolower($type)) {
    //             case 'completed':
    //                 $query->where('is_completed', true);
    //                 break;

    //             case 'declined':
    //                 $query->where('status', 'Denied');
    //                 break;

    //             case 'live':
    //                 $query->where('status', 'Live')
    //                     ->where('is_completed', false);
    //                 break;

    //             case 'pending':
    //                 $query->where('status', 'Offline');
    //                 break;

    //             case 'paused':
    //                 $query->where('status', 'Paused');
    //                 break;

    //             case 'flagged':
    //                 $query->where('status', 'Flagged');
    //                 break;

    //             default:
    //                 break;
    //         }
    //     }

    //     return $query->orderBy(
    //         'created_at',
    //         'DESC'
    //     )->paginate(
    //         $per_page,
    //         ['*'],
    //         'page',
    //         $page
    //     );
    // }


    public function getCampaignsByPagination($id, $type, $per_page, $page = null)
    {
        $columns = [
            'id',
            'user_id',
            'job_id',
            'post_title',
            'campaign_amount',
            'total_amount',
            'number_of_staff',
            'completed_count',
            'pending_count',
            'currency',
            'status',
            'is_completed',
            'created_at',
        ];

        $query = Campaign::with(['campaignType:id,name,url'])
            ->select($columns)
            ->where('user_id', $id);

        $filters = [
            'completed' => fn($q) => $q->where('is_completed', true),
            'declined'  => fn($q) => $q->where('status', 'Denied'),
            'live'      => fn($q) => $q->where('status', 'Live')->where('is_completed', false),
            'pending'   => fn($q) => $q->where('status', 'Offline'),
            'paused'    => fn($q) => $q->where('status', 'Paused'),
            'flagged'   => fn($q) => $q->where('status', 'Flagged'),
        ];

        if (!empty($type) && isset($filters[$type])) {
            $filters[$type]($query);
        }

        return $query->orderByDesc('created_at')
            ->paginate($per_page, ['*'], 'page', $page);
    }

    public function getUserCampaigns($id)
    {
        return Campaign::where(
            'user_id',
            $id
        )
            ->get([
                'id',
                'post_title',
                'job_id',
                'user_id'
            ]);
    }


    public function getCampaignsByAdmin($type, $page = null)
    {
        $query = Campaign::query();
        if (!empty($type)) {
            $query->where(
                'status',
                $type
            );
        }
        return $query->orderBy(
            'created_at',
            'DESC'
        )->paginate(
            20,
            ['*'],
            'page',
            $page
        );
    }
    public function getCampaignById($id, $userId = null)
    {
        $query = Campaign::with(['campaignType', 'campaignCategory'])->where(
            'id',
            $id
        );
        if ($userId) {
            $query->where(
                'user_id',
                $userId
            );
        }

        return $query->first();
    }

    public function getCampaignByJobId($id, $userId = null)
    {
        $query = Campaign::where(
            'job_id',
            $id
        );
        if ($userId) {
            $query->where(
                'user_id',
                $userId
            );
        }

        return $query->first();
    }


    public function availableJobs()
    {
        Campaign::where(
            'status',
            'Live'
        )->where(
            'is_completed',
            false
        )->orderBy(
            'created_at',
            'ASC'
        )->get();
    }
    public function processPaymentTransaction($user, $campaign, $amount, $currency, $channel)
    {
        $ref = time();

        PaymentTransaction::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'reference' => $ref,
            'amount' => $amount,
            'status' => 'successful',
            'balance' => app(WalletRepositoryModel::class)->getWalletBalance($user->id),
            'currency' => $currency,
            'channel' => $channel,
            'type' => 'campaign_posted',
            'description' => $campaign->post_title . ' Campaign'
        ]);
        return true;
    }

    public function updateAdminWallet($percent, $currency)
    {
        $adminWallet = Wallet::where('user_id', '1')->first();

        if ($currency == 'NGN') {
            $adminWallet->balance += $percent;
        } else {
            $adminWallet->usd_balance += $percent;
        }

        $adminWallet->save();
    }

    public function updateCampaignDetails($campaignId, $numberOfStaff, $totalAmount)
    {
        $campaign = Campaign::where('id', $campaignId)->first();

        $campaign->extension_references = null;
        $campaign->number_of_staff += $numberOfStaff;
        $campaign->total_amount += $totalAmount;
        $campaign->is_completed = false;
        $campaign->save();

        return $campaign;
    }

    public function createPaymentTransaction($userId, $campaignId, $amount)
    {
        $ref = time();

        PaymentTransaction::create([
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'reference' => $ref,
            'amount' => $amount,
            'status' => 'successful',
            'currency' => 'NGN',
            'balance' => app(WalletRepositoryModel::class)->getWalletBalance($userId),
            'channel' => 'paystack',
            'type' => 'edit_campaign_payment',
            'description' => 'Extend Campaign Payment'
        ]);
    }

    public function getCampaignActivities($campaignId, $userId)
    {
        return Campaign::with(['completed' => function ($query) {
            $query->where('status', 'Pending');
        }])
            ->where('job_id', $campaignId)
            ->where('user_id', $userId)
            ->select(['id', 'job_id', 'post_title'])
            ->get();
    }


    public function logAdminTransaction($amount, $currency, $channel, $user)
    {
        $ref = time();

        PaymentTransaction::create([
            'user_id' => 1,
            'campaign_id' => '1',
            'reference' => $ref,
            'amount' => $amount,
            'status' => 'successful',
            'currency' => $currency,
            'channel' => $channel,
            'balance' => app(WalletRepositoryModel::class)->getWalletBalance(1),
            'type' => 'campaign_revenue',
            'description' => 'Campaign revenue from ' . $user->name,
            'tx_type' => 'Credit',
            'user_type' => 'admin'
        ]);
    }
}
