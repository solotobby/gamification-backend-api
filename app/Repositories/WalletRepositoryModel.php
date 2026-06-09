<?php

namespace App\Repositories;

use App\Models\Currency;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;

class WalletRepositoryModel
{
    // ─── Helpers ────────────────────────────────────────────────────────────────

    public function mapCurrency(string $currency): string
    {
        return match (strtolower($currency)) {
            'naira', 'ngn'  => 'NGN',
            'dollar', 'usd' => 'USD',
            default         => strtoupper($currency),
        };
    }

    private function getBalanceFromWallet(Wallet $wallet): float
    {
        return match ($this->mapCurrency($wallet->base_currency)) {
            'NGN'   => $wallet->balance,
            'USD'   => $wallet->usd_balance,
            default => $wallet->bonus,
        };
    }

    private function getWallet(int $userId): ?Wallet
    {
        return Wallet::where('user_id', $userId)->first();
    }

    // ─── Wallet CRUD ─────────────────────────────────────────────────────────────

    public function createWallet($user, string $currency): Wallet
    {
        return Wallet::create([
            'user_id'       => $user->id,
            'balance'       => '0.00',
            'base_currency' => strtoupper($currency),
        ]);
    }

    public function walletDetails($user, $currency = null): array
    {
        $wallet = $this->getWallet($user->id) ?? $this->createWallet($user, $currency ?? 'NGN');

        return [
            'id'            => $wallet->id,
            'user_id'       => $wallet->user_id,
            'balance'       => $this->getBalanceFromWallet($wallet),
            'user_type'     => $wallet->user_type,
            'base_currency' => $wallet->base_currency,
            'created_at'    => $wallet->created_at,
            'updated_at'    => $wallet->updated_at,
        ];
    }

    public function updateWalletBaseCurrency($user, int $currencyId): bool
    {
        $currency = Cache::remember("currency.id.{$currencyId}", 3600, fn() => Currency::find($currencyId));
        if (!$currency) return false;

        $wallet = $this->getWallet($user->id);
        if (!$wallet) return false;

        $wallet->base_currency = $currency->code;
        $wallet->save();
        return true;
    }

    // ─── Balance Checks ──────────────────────────────────────────────────────────

    public function getWalletBalance(int $userId): float
    {
        $wallet = $this->getWallet($userId);
        return $wallet ? $this->getBalanceFromWallet($wallet) : 0;
    }

    public function checkWalletBalance($user, string $currency, float $amount): bool
    {
        $wallet = $this->getWallet($user->id);
        if (!$wallet) return false;

        return match ($this->mapCurrency($currency)) {
            'NGN'   => $wallet->balance >= $amount,
            'USD'   => $wallet->usd_balance >= $amount,
            default => $wallet->bonus >= $amount,
        };
    }

    // ─── Debit / Credit ──────────────────────────────────────────────────────────

    public function debitWallet($user, string $currency, float $amount): bool
    {
        $wallet = $this->getWallet($user->id);
        if (!$wallet) return false;

        switch ($this->mapCurrency($currency)) {
            case 'NGN':
                if ($wallet->balance < $amount) return false;
                $wallet->balance -= $amount;
                break;
            case 'USD':
                if ($wallet->usd_balance < $amount) return false;
                $wallet->usd_balance -= $amount;
                break;
            default:
                return false;
        }

        return (bool) $wallet->save();
    }

    public function creditWallet($user, string $currency, float $amount): bool
    {
        $wallet = $this->getWallet($user->id);
        if (!$wallet) return false;

        switch ($this->mapCurrency($currency)) {
            case 'NGN':
                $wallet->balance += $amount;
                break;
            case 'USD':
                $wallet->usd_balance += $amount;
                break;
            default:
                return false;
        }

        return (bool) $wallet->save();
    }

    public function creditAdminWallet(int $userId, float $amount): void
    {
        $wallet = $this->getWallet($userId);
        if (!$wallet) return;

        $wallet->balance += $amount;
        $wallet->save();
    }

    // ─── Transactions ────────────────────────────────────────────────────────────

    public function checkReferralCommission(string $currency): mixed
    {
        return Cache::remember("currency.commission.{$currency}", 3600, fn() =>
            Currency::where('code', $currency)->value('referral_commission')
        );
    }

    public function getUserTransactions($user, $page = null, $type = null, $search = null)
    {
        $query = PaymentTransaction::where('user_id', $user->id)
            ->where('status', 'successful')
            ->where('user_type', 'regular');

        if ($type && in_array($type, ['credit', 'debit'])) {
            $query->where('tx_type', $type);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('amount', 'LIKE', "%{$search}%");
            });
        }

        return $query->latest()->paginate(20, ['*'], 'page', $page);
    }

    public function createTransaction(
        $user,
        float $amount,
        string $ref,
        $campId,
        string $baseCurrency,
        string $type,
        string $description,
        string $txType,
        $referral = null,
    ) {
        if ($referral) {
            $transactionType        = 'referer_bonus';
            $transactionDescription = 'Referrer Bonus from ' . $description;
        } else {
            $isFirst                = !PaymentTransaction::where('user_id', $user->id)->exists();
            $transactionType        = $isFirst ? 'upgrade_payment' : $type;
            $transactionDescription = $isFirst ? 'Upgrade Payment' : $description;
        }

        return PaymentTransaction::create([
            'user_id'     => $user->id,
            'campaign_id' => $campId,
            'reference'   => $ref,
            'amount'      => $amount,
            'status'      => 'successful',
            'currency'    => $baseCurrency,
            'channel'     => 'freebyz',
            'type'        => $transactionType,
            'description' => $transactionDescription,
            'tx_type'     => $txType,
            'user_type'   => 'regular',
        ]);
    }

    public function createAdminTransaction(array $data): PaymentTransaction
    {
        return PaymentTransaction::create($data);
    }
}

// class WalletRepositoryModel
// {
//     public function createWallet($user, $currency)
//     {
//         $wallet = Wallet::create(['user_id' => $user->id, 'balance' => '0.00', 'base_currency' => $currency]);
//         return $wallet;
//     }

//     public function walletDetails($user, $currency = null)
//     {

//         $wallet = Wallet::where('user_id', $user->id)->first();

//         if (!$wallet) {
//             $currency = strtoupper($currency ?? 'NGN');
//             $wallet = $this->createWallet($user, $currency);
//         }
//         // Map currency
//         $balance = match (strtoupper($wallet->base_currency)) {
//             'NAIRA', 'NGN' => $wallet->balance,
//             'DOLLAR', 'USD' => $wallet->usd_balance,
//             default => $wallet->bonus,
//         };
//         return [
//             'id' => $wallet->id,
//             'user_id' => $wallet->user_id,
//             'balance' => $balance,
//             'user_type' => $wallet->user_type,
//             'base_currency' => $wallet->base_currency,
//             'created_at' => $wallet->created_at,
//             'updated_at' => $wallet->updated_at,
//         ];
//     }

//     public function updateWallet($user, $currency, $amount)
//     {
//         $wallet = Wallet::where(
//             'user_id',
//             $user->id
//         )->where('', '',    $currency)->where(
//             'amount',
//             $amount
//         )->first();
//     }

//     public function updateWalletBaseCurrency($user, $currencyId)
//     {
//         $currency = Currency::where('id', $currencyId)->first();
//         $wallet = Wallet::where(
//             'user_id',
//             $user->id
//         )->first();
//         $wallet->base_currency = $currency->code;
//         $wallet->save();
//         return true;
//     }


//     public function checkWalletBalance($user, $currency, $amount)
//     {
//         $wallet = Wallet::where(
//             'user_id',
//             $user->id
//         )->first();
//         if (!$wallet) {
//             return false;
//         }

//         // Check balance based on the currency
//         switch (strtolower($currency)) {
//             case 'naira':
//                 return $wallet->balance >= $amount;

//             case 'ngn':
//                 return $wallet->balance >= $amount;

//             case 'dollar':
//                 return $wallet->usd_balance >= $amount;

//             case 'usd':
//                 return $wallet->usd_balance >= $amount;

//             default:
//                 return $wallet->bonus >= $amount;
//         }
//     }


//     public function checkReferralCommission($mapCurrency)
//     {
//         $currency = Currency::where('code', $mapCurrency)->first();
//         return $currency->referral_commission;
//     }

//     public function mapCurrency($currency): string
//     {
//         return [
//             'naira'  => 'NGN',
//             'ngn'    => 'NGN',
//             'dollar' => 'USD',
//             'usd'    => 'USD',
//         ][strtolower($currency)] ?? $currency;
//     }

//     public function getWalletBalance($userId)
//     {
//         $wallet = Wallet::where('user_id', $userId)->first();

//         if (!$wallet) {
//             return 0;
//         }

//         return match ($wallet->base_currency) {
//             'NGN' => $wallet->balance,
//             'naira' => $wallet->balance,
//             'USD' => $wallet->usd_balance,
//             'dollar' => $wallet->usd_balance,
//             default => $wallet->base_currency_balance,
//         };
//     }

//     public function debitWallet($user, $currency, $amount)
//     {

//         $wallet = Wallet::where(
//             'user_id',
//             $user->id
//         )->first();

//         if (!$wallet) {
//             return false;
//         }

//         // Process debit based on the currency
//         switch (strtoupper($currency)) {
//             case 'NAIRA':
//             case 'NGN':
//                 if ($wallet->balance < $amount) {
//                     return false;
//                 }
//                 $wallet->balance -= $amount;
//                 break;

//             case 'DOLLAR':
//             case 'USD':
//                 if ($wallet->usd_balance < $amount) {
//                     return false;
//                 }
//                 $wallet->usd_balance -= $amount;
//                 break;

//             default:
//                 return false;
//         }
//         // Save the updated wallet
//         if ($wallet->save()) return true;
//     }

//     public function getUserTransactions($user, $page = null, $type = null, $search = null)
//     {
//         $query = PaymentTransaction::where('user_id', $user->id)
//             ->where('status', 'successful')
//             ->where('user_type', 'regular');

//         // Filter by credit or debit
//         if ($type && in_array($type, ['credit', 'debit'])) {
//             $query->where('tx_type', $type);
//         }

//         // Search
//         if ($search) {
//             $query->where(function ($q) use ($search) {
//                 $q->where('reference', 'LIKE', "%{$search}%")
//                     ->orWhere('description', 'LIKE', "%{$search}%")
//                     ->orWhere('amount', 'LIKE', "%{$search}%");
//             });
//         }

//         return $query->latest()
//             ->paginate(20, ['*'], 'page', $page);
//     }
//     public function creditWallet($user, $currency, $amount)
//     {
//         $wallet = Wallet::where(
//             'user_id',
//             $user->id
//         )->first();

//         if (!$wallet) {
//             return false;
//         }

//         // Process credit based on the currency
//         switch (strtoupper($currency)) {
//             case 'NAIRA':
//             case 'NGN':
//                 $wallet->balance += $amount;
//                 break;

//             case 'DOLLAR':
//             case 'USD':
//                 $wallet->usd_balance += $amount;
//                 break;

//             default:
//                 return false;
//         }

//         // Save the updated wallet
//         if ($wallet->save())  return true;
//     }

//     public function creditAdminWallet($userId, $amount)
//     {
//         $wallet = Wallet::where('user_id', $userId)->first();
//         $wallet->balance += $amount;
//         $wallet->save();
//     }

//     public function createTransaction(
//         $user,
//         $amount,
//         $ref,
//         $campId,
//         $baseCurrency,
//         $type,
//         $description,
//         $txType,
//         $referral = null,
//     ) {
//         // Check if this is the user's first transaction
//         $isFirstTransaction = !PaymentTransaction::where('user_id', $user->id)->exists();

//         if ($referral) {
//             $transactionType = 'referer_bonus';
//             $transactionDescription = 'Referrer Bonus from ' . $description;
//         }
//         $transactionType = $isFirstTransaction ? 'upgrade_payment' : $type;
//         $transactionDescription = $isFirstTransaction ? 'Upgrade Payment' : $description;

//         $transaction = PaymentTransaction::create([
//             'user_id' => $user->id,
//             'campaign_id' => $campId,
//             'reference' => $ref,
//             'amount' => $amount,
//             'status' => 'successful',
//             'currency' => $baseCurrency,
//             'channel' => 'freebyz',
//             'type' => $transactionType,
//             'description' => $transactionDescription,
//             'tx_type' => $txType,
//             'user_type' => 'regular'
//         ]);

//         return $transaction;
//     }


//     public function createAdminTransaction($data)
//     {
//         return PaymentTransaction::create($data);
//     }
// }
