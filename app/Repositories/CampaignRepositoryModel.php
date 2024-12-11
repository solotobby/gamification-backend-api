<?php

namespace App\Repositories;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Wallet;
use App\Models\PaymentTransaction;

class CampaignRepositoryModel
{
    public function __construct() {}

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

    public function getCampaignsByPagination($id)
    {
        return Campaign::where(
            'user_id',
            $id
        )->orderBy(
            'created_at',
            'DESC'
        )->paginate(5);
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
            'currency' => $currency,
            'channel' => $channel,
            'type' => 'campaign_posted',
            'description' => $campaign->post_title . ' Campaign'
        ]);
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
            'type' => 'campaign_revenue',
            'description' => 'Campaign revenue from ' . $user->name,
            'tx_type' => 'Credit',
            'user_type' => 'admin'
        ]);
    }
}
