<?php

namespace App\Services\Admin;

use Throwable;
use App\Exceptions\BadRequestException;
use App\Repositories\Admin\CurrencyRepositoryModel;
use App\Validators\Admin\CurrencyValidator;

class CurrencyService
{
    protected $currency, $validator;
    public function __construct(
        CurrencyRepositoryModel $currency,
        CurrencyValidator $validator
    ) {
        $this->currency = $currency;
        $this->validator = $validator;
    }


    public function getCurrenciesList()
    {
        try {
            $currencies = $this->currency->getCurrenciesList();
            if ($currencies->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No currencies found',
                    'data' => []
                ], 404);
            }
            return response()->json([
                'status' => true,
                'message' => 'List of currency retrieved successfully',
                'data' => $currencies
            ], 200);
        } catch (Throwable $e) {
            // return $e;
            return response()->json([
                'status' => false,
                'message' => 'Error processing request: ' . $e->getMessage(),
            ], 400);
        }
    }


    public function getCurrency($id)
    {
        try {
            $currency = $this->currency->getCurrencyById($id);

            // Check if the currency exists
            if (!$currency) {
                return response()->json([
                    'status' => false,
                    'message' => 'Currency not found.',
                ], 404);
            }
            return response()->json([
                'status' => true,
                'message' => 'List of currency retrieved successfully',
                'data' => $currency
            ], 200);
        } catch (Throwable $e) {
            // return $e;
            return response()->json([
                'status' => false,
                'message' => 'Error processing request: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function updateCurrency($request)
    {
        $this->validator->validateUpdateCurrency($request);
        try {
            // Find the currency by ID
            $currency = $this->currency->getCurrencyById($request->id);

            // Check if the currency exists
            if (!$currency) {
                return response()->json([
                    'status' => false,
                    'message' => 'Currency not found.',
                ], 404);
            }

            // Update the currency fields with validated data
            $currency->base_rate = $request->base_rate;
            $currency->referral_commission = $request->referral_commission;
            $currency->upgrade_fee = $request->upgrade_fee;
            $currency->priotize = $request->priotize;
            $currency->allow_upload = $request->allow_upload;
            $currency->min_upgrade_amount = $request->min_upgrade_amount;

            // Save the updated currency
            $currency->save();

            // Return a success response in JSON format
            return response()->json([
                'status' => true,
                'message' => 'Currency updated successfully!',
                'data' => $currency,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error processing request: ' . $e->getMessage(),
            ], 500);
        }
    }
}
