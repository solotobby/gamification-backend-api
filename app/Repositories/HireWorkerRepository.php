<?php

namespace App\Repositories;

use App\Models\PaymentTransaction;
use App\Models\Portfolio;
use App\Models\Skill;
use App\Models\SkillAsset;
use App\Models\ProfessionalProficiencyLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HireWorkerRepository
{
    public function getWorkers(array $filters = [], $page = null)
    {
        $query = SkillAsset::query()->where('status', 'active');

        if (!empty($filters['skill_id'])) {
            $query->where('skill_id', $filters['skill_id']);
        }

        if (!empty($filters['availability'])) {
            $query->where('availability', $filters['availability']);
        }

        if (!empty($filters['year_experience'])) {
            match ($filters['year_experience']) {
                '0-2'  => $query->whereBetween('year_experience', [0, 2]),
                '3-5'  => $query->whereBetween('year_experience', [3, 5]),
                '6-10' => $query->whereBetween('year_experience', [6, 10]),
                '10+'  => $query->where('year_experience', '>=', 10),
                default => null,
            };
        }

        return $query->with(['user:id,name,email,phone', 'skill:id,name', 'profeciencyLevel:id,name'])
            ->latest()
            ->paginate(20, ['*'], 'page', $page);
    }

    public function getWorkerById($id)
    {
        return SkillAsset::with(['user:id,name,email,phone', 'skill:id,name', 'profeciencyLevel:id,name'])
            ->where('status', 'active')
            ->findOrFail($id);
    }

    public function getWorkerPortfolio($userId)
    {
        return Portfolio::where('user_id', $userId)->get();
    }

    public function hasUserPurchasedPoint($skillAssetId, $userId)
    {
        return DB::table('skill_user')
            ->where('skill_asset_id', $skillAssetId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function purchasePoint($skillAssetId, $userId, $amount, $currency)
    {
        Log::info($currency);
        PaymentTransaction::create([
            'user_id'     => $userId,
            'campaign_id' => '1',
            'reference'   => time(),
            'amount'      => $amount,
            'balance'     => walletBalance()->balance,
            'status'      => 'successful',
            'currency'    => $currency->code,
            'channel'     => 'paystack',
            'type'        => 'point_purchase',
            'description' => auth()->user()->name . ' purchased point',
            'tx_type'     => 'Debit',
            'user_type'   => 'regular',
        ]);

        DB::table('skill_user')->insert([
            'skill_asset_id' => $skillAssetId,
            'user_id'        => $userId,
        ]);
    }

    public function getSkills()
    {
        return Skill::all(['id', 'name']);
    }

    public function getProficiencyLevels()
    {
        return ProfessionalProficiencyLevel::where('status', 'active')->get(['id', 'name']);
    }
}
