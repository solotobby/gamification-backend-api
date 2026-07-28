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
    // public function getWorkers(array $filters = [], $page = null)
    // {
    //     $query = SkillAsset::query()->where('status', 'active');

    //     if (!empty($filters['skill_id'])) {
    //         $query->where('skill_id', $filters['skill_id']);
    //     }

    //     if (!empty($filters['availability'])) {
    //         $query->where('availability', $filters['availability']);
    //     }

    //     if (!empty($filters['year_experience'])) {
    //         match ($filters['year_experience']) {
    //             '0-2'  => $query->whereBetween('year_experience', [0, 2]),
    //             '3-5'  => $query->whereBetween('year_experience', [3, 5]),
    //             '6-10' => $query->whereBetween('year_experience', [6, 10]),
    //             '10+'  => $query->where('year_experience', '>=', 10),
    //             default => null,
    //         };
    //     }

    //     return $query->with(['user:id,name,email,phone', 'skill:id,name', 'profeciencyLevel:id,name'])
    //         ->latest()
    //         ->paginate(20, ['*'], 'page', $page);
    // }

    public function getWorkers(array $filters = [], $page = null)
    {
        $purchasedIds = DB::table('skill_user')
            ->where('user_id', auth()->id())
            ->pluck('skill_asset_id');

        $query = SkillAsset::query()
            ->where('status', 'active')
            ->whereNotIn('id', $purchasedIds);

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


     public function getWorkersPublic(array $filters = [], $page = 1, $perPage = 20)
    {
        // $purchasedIds = DB::table('skill_user')
        //     ->where('user_id', auth()->id())
        //     ->pluck('skill_asset_id');

        $query = SkillAsset::query()
            ->where('status', 'active');
            // ->whereNotIn('id', $purchasedIds);

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
            ->paginate($perPage, ['*'], 'page', $page);
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

    public function purchasePoint($skillAssetId, $userId, $amount, $currency, $worker)
    {
        // Log::info($currency);
        PaymentTransaction::create([
            'user_id'     => $userId,
            'campaign_id' => '1',
            'reference'   => time(),
            'amount'      => $amount,
            'balance'     => app(WalletRepositoryModel::class)->getWalletBalance($userId),
            'status'      => 'successful',
            'currency'    => $currency->code,
            'channel'     => 'paystack',
            'type'        => 'point_purchase',
            'description' => $worker->user->name . ' Hire worker point purchase',
            'tx_type'     => 'Debit',
            'user_type'   => 'regular',
        ]);

        DB::table('skill_user')->insert([
            'skill_asset_id' => $skillAssetId,
            'user_id'        => $userId,
            'created_at'    => now(),
            'updated_at'    => now(),
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

    public function createSkillAsset(array $data, $userId)
    {
        return SkillAsset::create(array_merge($data, ['user_id' => $userId, 'status' => 'pending']));
    }

    public function getPurchasedWorkers($userId, $page = null)
    {
        $purchasedSkillAssetIds = DB::table('skill_user')
            ->where('user_id', $userId)
            ->pluck('skill_asset_id');

        return SkillAsset::with(['user:id,name,email,phone', 'skill:id,name', 'profeciencyLevel:id,name'])
            ->whereIn('id', $purchasedSkillAssetIds)
            ->latest()
            ->paginate(20, ['*'], 'page', $page);
    }

    public function getMySkillAsset($userId)
    {
        return SkillAsset::with(['skill:id,name', 'profeciencyLevel:id,name'])
            ->where('user_id', $userId)
            // ->where('status', 'active')
            ->first();
    }

    public function updateSkillAsset($id, array $data, $userId)
    {
        $skill = SkillAsset::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        $skill->update($data);

        return $skill->fresh(['skill:id,name', 'profeciencyLevel:id,name']);
    }

    public function createPortfolio(array $portfolios, $skillAssetId, $userId)
    {
        $records = array_map(fn($p) => array_merge($p, [
            'skill_id'   => $skillAssetId,
            'user_id'    => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]), $portfolios);

        Portfolio::insert($records);
    }

    public function updatePortfolio(array $portfolios, $skillAssetId, $userId)
    {
        // Delete existing and re-insert (simplest approach)
        Portfolio::where('skill_id', $skillAssetId)
            ->where('user_id', $userId)
            ->delete();

        if (!empty($portfolios)) {
            $this->createPortfolio($portfolios, $skillAssetId, $userId);
        }
    }
}
