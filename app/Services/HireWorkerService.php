<?php

namespace App\Services;

use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Repositories\CampaignRepositoryModel;
use App\Repositories\HireWorkerRepository;
use App\Repositories\WalletRepositoryModel;
use Illuminate\Support\Facades\DB;
use Throwable;

class HireWorkerService
{
    protected $repo;
    protected $campaignModel;
    protected $campaignService;
    protected $currencyModel;
    protected $walletModel;

    public function __construct(
        HireWorkerRepository $repo,
        CampaignRepositoryModel $campaignModel,
        WalletRepositoryModel $walletModel,
        CurrencyRepositoryModel $currencyModel,
        CampaignService $campaignService,
    ) {
        $this->repo = $repo;
        $this->campaignModel = $campaignModel;
        $this->walletModel = $walletModel;
        $this->currencyModel = $currencyModel;
        $this->campaignService = $campaignService;
    }

    public function getWorkers($request)
    {
        try {
            $filters = $request->only(['skill_id', 'availability', 'year_experience']);
            $workers = $this->repo->getWorkers($filters);

            $data = [];
            foreach ($workers as $worker) {
                $data[] = $this->formatWorkerSummary($worker);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'Workers retrieved successfully.',
                'data'       => $data,
                'pagination' => $this->buildPagination($workers),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving workers.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getWorker($id)
    {
        try {
            $user   = auth()->user();
            $worker = $this->repo->getWorkerById($id);

            $hasPurchased = $this->repo->hasUserPurchasedPoint($worker->id, $user->id);

            $portfolio = $this->repo->getWorkerPortfolio($worker->user_id);

            // Only expose contact info if point has been purchased
            $professionalInfo = $hasPurchased
                ? [
                    'email'  => $worker->user->email,
                    'phone'  => $worker->user->phone,
                ]
                : null;

            return response()->json([
                'status'  => true,
                'message' => 'Worker retrieved successfully.',
                'data'    => array_merge(
                    $this->formatWorkerDetail($worker),
                    [
                        'portfolio'          => $portfolio,
                        'has_purchased'      => $hasPurchased,
                        'professional_info'  => $professionalInfo,
                        'point_required'     => !$hasPurchased ? 1 : null,
                        'point_cost_ngn'     => !$hasPurchased ? 500 : null,
                    ]
                ),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Worker not found.',
            ], 404);
        }
    }


    public function createSkillAsset($request)
    {
        try {
            $validated = $request->validate([
                'title'               => 'required|string|max:255',
                'skill_id'            => 'required|exists:skills,id',
                'description'         => 'required|string',
                'min_price'           => 'required|numeric',
                'max_price'           => 'required|numeric',
                'profeciency_level'   => 'required|exists:professionals_proficiency_levels,id',
                'year_experience'     => 'required|in:0-2,3-5,6-10,10+',
                'location'            => 'required|string',
                'availability'        => 'required',

                // Portfolio (optional at creation)
                'portfolio'           => 'nullable|array',
                'portfolio.*.title'   => 'required_with:portfolio|string|max:255',
                'portfolio.*.description' => 'required_with:portfolio|string',
            ]);

            $skill = $this->repo->createSkillAsset(
                collect($validated)->except('portfolio')->toArray(),
                auth()->id()
            );

            if (!empty($validated['portfolio'])) {
                $this->repo->createPortfolio($validated['portfolio'], $skill->skill_id, auth()->id());
            }

            return response()->json([
                'status'  => true,
                'message' => 'Skill created successfully.',
                'data'    => array_merge(
                    $this->formatWorkerDetail($skill),
                    ['portfolio' => $this->repo->getWorkerPortfolio(auth()->id())]
                ),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error creating skill.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateSkillAsset($request, $id)
    {
        try {
            $validated = $request->validate([
                'title'               => 'sometimes|string|max:255',
                'skill_id'            => 'sometimes|exists:skills,id',
                'description'         => 'sometimes|string',
                'min_price'           => 'sometimes|numeric',
                'max_price'           => 'sometimes|numeric',
                'profeciency_level'   => 'sometimes|exists:professionals_proficiency_levels,id',
                'year_experience'     => 'sometimes',
                'location'            => 'sometimes|string',
                'availability'        => 'sometimes',

                // Portfolio (optional on update, replaces existing)
                'portfolio'               => 'nullable|array',
                'portfolio.*.title'       => 'required_with:portfolio|string|max:255',
                'portfolio.*.description' => 'required_with:portfolio|string',
            ]);



            // return $validated;
            $skill = $this->repo->updateSkillAsset(
                $id,
                collect($validated)->except('portfolio')->toArray(),
                auth()->id()
            );

            if (array_key_exists('portfolio', $validated)) {
                $this->repo->updatePortfolio($validated['portfolio'] ?? [], $skill->skill_id, auth()->id());
            }

            return response()->json([
                'status'  => true,
                'message' => 'Skill updated successfully.',
                'data'    => array_merge(
                    $this->formatWorkerDetail($skill),
                    ['portfolio' => $this->repo->getWorkerPortfolio(auth()->id())]
                ),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error updating skill.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getPurchasedWorkers()
    {
        try {
            $workers = $this->repo->getPurchasedWorkers(auth()->id());

            $data = [];
            foreach ($workers as $worker) {
                $data[] = $this->formatWorkerSummary($worker);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'Purchased workers retrieved successfully.',
                'data'       => $data,
                'pagination' => $this->buildPagination($workers),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving purchased workers.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getMySkill()
    {
        try {
            $skill = $this->repo->getMySkillAsset(auth()->id());

            if (!$skill) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have not created a skill yet.',
                ], 404);
            }

            $portfolio = $this->repo->getWorkerPortfolio(auth()->id());

            return response()->json([
                'status'  => true,
                'message' => 'Skill retrieved successfully.',
                'data'    => array_merge(
                    $this->formatWorkerDetail($skill),
                    [
                        'portfolio'          => $portfolio,

                    ]
                ),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving skill.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }



    public function purchasePoint($id)
    {
        try {
            $user   = auth()->user();
            $worker = $this->repo->getWorkerById($id);

            // Already purchased
            if ($this->repo->hasUserPurchasedPoint($worker->id, $user->id)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'You have already purchased access to this worker.',
                ], 409);
            }

            $baseCurrency = $user->wallet->base_currency;
            $mapCurrency  = $this->walletModel->mapCurrency($baseCurrency);
            $currency     = $this->currencyModel->getCurrencyByCode($mapCurrency);

            $unitPrice = 500;

            if ($currency->code !== 'NGN') {
                $rate = $this->campaignService->currencyConversion('NGN', $currency->code);
                $unitPrice *= $rate;
            }

            $amount = $unitPrice;

            // Insufficient wallet balance
            if (!checkWalletBalance($user, $currency, $amount)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Insufficient wallet balance. Please fund your wallet.',
                ], 402);
            }

            DB::beginTransaction();

            // 🔹 Debit wallet
            $debit = debitWallet($user, $currency, $amount);

            if (!$debit) {
                DB::rollBack();
                return response()->json([
                    'status'  => false,
                    'message' => 'Failed to debit wallet. Please try again.',
                ], 500);
            }

            // 🔹 Save purchase
            $this->repo->purchasePoint($worker->id, $user->id, $amount, $currency);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Point purchased successfully.',
                'data'    => [
                    'email' => $worker->user->email,
                    'phone' => $worker->user->phone,
                ],
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => false,
                'message' => 'Error processing point purchase.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getFilters()
    {
        try {
            return response()->json([
                'status'  => true,
                'message' => 'Filters retrieved successfully.',
                'data'    => [
                    'skills'              => $this->repo->getSkills(),
                    'proficiency_levels'  => $this->repo->getProficiencyLevels(),
                    'year_experience'     => ['0-2', '3-5', '6-10', '10+'],
                    'availability'        => ['full-time', 'part-time', 'remote', 'contract'],
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Error retrieving filters.',
            ], 500);
        }
    }

    // ─── Private Helpers ────────────────────────────────────────────────────────

    private function formatWorkerSummary($worker)
    {
        return [
            'id'               => $worker->id,
            'name'             => $worker->user->name ?? null,
            'title'            => $worker->title,
            'skill'            => $worker->skill->name ?? null,
            'proficiency'      => $worker->profeciencyLevel->name ?? null,
            'year_experience'  => $worker->year_experience,
            'availability'     => $worker->availability,
            'location'         => $worker->location,
            'min_price'        => $worker->min_price,
            'max_price'        => $worker->max_price,
        ];
    }

    private function formatWorkerDetail($worker)
    {
        return [
            'id'               => $worker->id,
            'name'             => $worker->user->name ?? null,
            'title'            => $worker->title,
            'description'      => $worker->description,
            'skill'            => $worker->skill->name ?? null,
            'skill_id'         => $worker->skill_id,
            'proficiency'      => $worker->profeciencyLevel->name ?? null,
            'year_experience'  => $worker->year_experience,
            'availability'     => $worker->availability,
            'location'         => $worker->location,
            'min_price'        => $worker->min_price,
            'max_price'        => $worker->max_price,
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
