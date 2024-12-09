<?php

namespace App\Services;

use App\Repositories\CampaignRepositoryModel;
use Throwable;

class CampaignService
{
    protected $campaignModel;
    public function __construct(CampaignRepositoryModel $campaignModel)
    {
        $this->campaignModel = $campaignModel;
    }

    public function create(array $data) {}

    public function getCategories()
    {
        try {
            // Fetch all active category
            $categories =  $this->campaignModel->listCategories();
            if (!$categories) {
                return response()->json([
                    'status' => false,
                    'message' => 'No categories found',
                    'data' => []
                ], 404);
            }

            $data = $categories->map(function ($category) {
                // Fetch subcategories for this category
                $subCategories = $this->campaignModel->listSubCategories($category->id);

                // Transform the subcategories
                $subCategoryData = $subCategories->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'amount' => $sub->amount,
                        'category_id' => $sub->category_id,
                        'name' => $sub->name,
                        //'amt_usd' => $sub->usd ?? $sub->amount,
                    ];
                });

                // Add subcategories under the category
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'subcategories' => $subCategoryData
                ];
            });
        } catch (Throwable  $e) {
            return response()->json([
                'status' => false,
                'error' => $e->getMessage(),
                'message' => 'Error processing request'
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Campaign Categories',
            'data' => $data
        ], 200);
    }
}
