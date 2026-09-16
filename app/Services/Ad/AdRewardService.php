<?php

namespace App\Services\Ad;

use App\Enums\Ad\AdStatus;
use App\Enums\Wallet\TransactionDirection;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionType;
use App\Models\Ad;
use App\Models\AdRewardAccount;
use App\Models\AdRewardTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Num;
use Illuminate\Support\Facades\DB;

class AdRewardService
{
    public function isEnabled(): bool
    {
        return (bool) config('wallet.ads_reward.enabled', true);
    }

    public function monthlyAmount(): float
    {
        return round((float) config('wallet.ads_reward.monthly_amount', 300), 2);
    }

    public function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    public function isEligible(User $user): bool
    {
        return $this->isEnabled() && $user->isVerified();
    }

    public function getWalletSummary(User $user): array
    {
        $user->loadMissing('wallet');

        $wallet = $user->wallet;
        $currency = $wallet->currency ?? (config('app.default_currency') ?: 'USD');
        $cashBalance = round((float) ($wallet?->balance->getAmount() ?? 0), 2);
        $rewardBalance = $this->getAvailableCredit($user);
        $totalBalance = round($cashBalance + $rewardBalance, 2);

        return [
            'balance' => $this->moneyData($totalBalance, $currency),
            'cash_balance' => $this->moneyData($cashBalance, $currency),
            'transferable_balance' => $this->moneyData($cashBalance, $currency),
            'ads_reward_credit' => $this->moneyData($rewardBalance, $currency),
            'reward_eligible' => $this->isEligible($user),
        ];
    }

    public function getAvailableCredit(User $user): float
    {
        if(! $this->isEligible($user)) {
            return 0;
        }

        $account = $this->currentAccount($user, false);

        if(empty($account) || $account->status !== 'active') {
            return 0;
        }

        return round((float) $account->available_amount, 2);
    }

    public function getAvailableAdFunds(User $user): float
    {
        $user->loadMissing('wallet');

        return round(((float) $user->wallet->balance->getAmount()) + $this->getAvailableCredit($user), 2);
    }

    public function grantCurrentMonth(User $user): ?AdRewardAccount
    {
        if(! $this->isEligible($user)) {
            return null;
        }

        return DB::transaction(function () use ($user) {
            $period = $this->currentPeriod();
            $account = AdRewardAccount::where('user_id', $user->id)
                ->where('period_month', $period)
                ->lockForUpdate()
                ->first();

            if($account) {
                if($account->status === 'frozen') {
                    $account->update(['status' => 'active']);
                }

                return $account;
            }

            $amount = $this->monthlyAmount();

            $account = AdRewardAccount::create([
                'user_id' => $user->id,
                'period_month' => $period,
                'monthly_amount' => $amount,
                'available_amount' => $amount,
                'used_amount' => 0,
                'status' => 'active',
                'expires_at' => now()->endOfMonth(),
            ]);

            $this->recordRewardTransaction($account, [
                'user_id' => $user->id,
                'period_month' => $period,
                'amount' => $amount,
                'transaction_type' => 'grant',
                'direction' => 'incoming',
                'metadata' => [
                    'source' => ['name' => config('ads.name')],
                    'reason' => 'monthly_verified_user_reward',
                ],
            ]);

            return $account;
        });
    }

    public function freezeCurrentMonth(User $user, string $reason = 'user_unverified'): void
    {
        DB::transaction(function () use ($user, $reason) {
            $account = AdRewardAccount::where('user_id', $user->id)
                ->where('period_month', $this->currentPeriod())
                ->lockForUpdate()
                ->first();

            if(empty($account) || $account->status === 'frozen') {
                return;
            }

            $account->update(['status' => 'frozen']);

            $this->recordRewardTransaction($account, [
                'user_id' => $user->id,
                'period_month' => $account->period_month,
                'amount' => 0,
                'transaction_type' => 'freeze',
                'direction' => 'neutral',
                'metadata' => [
                    'source' => ['name' => config('ads.name')],
                    'reason' => $reason,
                ],
            ]);
        });
    }

    public function setCurrentCredit(User $user, float $amount, string $reason = 'admin_adjust'): ?AdRewardAccount
    {
        if(! $this->isEligible($user)) {
            return null;
        }

        return DB::transaction(function () use ($user, $amount, $reason) {
            $account = $this->currentAccount($user, true, true);

            if(empty($account)) {
                return null;
            }

            $amount = round(max(0, $amount), 2);
            $currentAmount = round((float) $account->available_amount, 2);
            $delta = round($amount - $currentAmount, 2);

            $account->update([
                'available_amount' => $amount,
                'status' => 'active',
            ]);

            if($delta != 0.0) {
                $this->recordRewardTransaction($account, [
                    'user_id' => $user->id,
                    'period_month' => $account->period_month,
                    'amount' => abs($delta),
                    'transaction_type' => 'admin_adjust',
                    'direction' => $delta > 0 ? 'incoming' : 'outgoing',
                    'metadata' => [
                        'source' => ['name' => config('app.name')],
                        'reason' => $reason,
                        'previous_available_amount' => $currentAmount,
                        'new_available_amount' => $amount,
                    ],
                ]);
            }

            return $account;
        });
    }

    public function setCurrentStatus(User $user, bool $active, string $reason = 'admin_status_change'): void
    {
        if($active) {
            $this->grantCurrentMonth($user);

            return;
        }

        $this->freezeCurrentMonth($user, $reason);
    }

    public function resetMonthlyCredits(): int
    {
        $currentPeriod = $this->currentPeriod();
        $processed = 0;

        AdRewardAccount::where('period_month', '!=', $currentPeriod)
            ->whereIn('status', ['active', 'frozen'])
            ->chunkById(200, function($accounts) {
                foreach($accounts as $account) {
                    DB::transaction(function () use ($account) {
                        $lockedAccount = AdRewardAccount::whereKey($account->id)->lockForUpdate()->first();

                        if(empty($lockedAccount) || $lockedAccount->status === 'expired') {
                            return;
                        }

                        $expiredAmount = (float) $lockedAccount->available_amount;

                        if($expiredAmount > 0) {
                            $this->recordRewardTransaction($lockedAccount, [
                                'user_id' => $lockedAccount->user_id,
                                'period_month' => $lockedAccount->period_month,
                                'amount' => $expiredAmount,
                                'transaction_type' => 'expire',
                                'direction' => 'outgoing',
                                'metadata' => [
                                    'source' => ['name' => config('ads.name')],
                                    'reason' => 'monthly_reset_no_carryover',
                                ],
                            ]);
                        }

                        $lockedAccount->update([
                            'available_amount' => 0,
                            'status' => 'expired',
                        ]);
                    });
                }
            });

        User::where('verified', true)->chunkById(200, function($users) use (&$processed) {
            foreach($users as $user) {
                if($this->grantCurrentMonth($user)) {
                    $processed++;
                }
            }
        });

        return $processed;
    }

    public function allocateAdBudget(User $user, Ad $ad, float $amount, float $pricePerView): array
    {
        return DB::transaction(function () use ($user, $ad, $amount, $pricePerView) {
            $amount = round($amount, 2);
            $user = User::whereKey($user->id)->firstOrFail();
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $account = $this->isEligible($user) ? $this->currentAccount($user, true, true) : null;

            $rewardAvailable = ($account && $account->status === 'active') ? round((float) $account->available_amount, 2) : 0;
            $cashAvailable = round((float) $wallet->balance->getAmount(), 2);
            $totalAvailable = round($rewardAvailable + $cashAvailable, 2);

            if($totalAvailable < $amount) {
                return [
                    'success' => false,
                    'available' => $totalAvailable,
                ];
            }

            $rewardAmount = min($amount, $rewardAvailable);
            $cashAmount = round($amount - $rewardAmount, 2);

            if($rewardAmount > 0 && $account) {
                $account->update([
                    'available_amount' => round(((float) $account->available_amount - $rewardAmount), 2),
                    'used_amount' => round(((float) $account->used_amount + $rewardAmount), 2),
                ]);

                $this->recordRewardTransaction($account, [
                    'user_id' => $user->id,
                    'ad_id' => $ad->id,
                    'period_month' => $account->period_month,
                    'amount' => $rewardAmount,
                    'transaction_type' => 'spend',
                    'direction' => 'outgoing',
                    'metadata' => [
                        'source' => ['name' => config('ads.name')],
                        'reason' => 'ad_budget_allocation',
                        'price_per_view' => (float) $pricePerView,
                    ],
                ]);
            }

            if($cashAmount > 0) {
                $wallet->update([
                    'balance' => round($cashAvailable - $cashAmount, 2),
                ]);

                $wallet->transactions()->create([
                    'amount' => $cashAmount,
                    'transaction_type' => TransactionType::ADVERTISING,
                    'status' => TransactionStatus::COMPLETED,
                    'direction' => TransactionDirection::OUTGOING,
                    'currency' => $wallet->currency,
                    'metadata' => [
                        'ad_id' => $ad->id,
                        'source' => ['name' => config('ads.name')],
                        'reason' => 'ad_budget_allocation',
                        'funding_source' => 'cash_balance',
                        'price_per_view' => (float) $pricePerView,
                    ],
                ]);
            }

            return [
                'success' => true,
                'reward_amount' => $rewardAmount,
                'cash_amount' => $cashAmount,
                'available' => $totalAvailable,
            ];
        });
    }

    public function refundUnusedAdBudget(Ad $ad): array
    {
        $spentBudget = round((float) $ad->spent_budget, 2);
        $rewardAllocated = $this->rewardAllocatedForAd($ad);
        $cashAllocated = $this->cashAllocatedForAd($ad);

        $rewardSpent = min($spentBudget, $rewardAllocated);
        $cashSpent = max(0, round($spentBudget - $rewardAllocated, 2));
        $rewardRefund = max(0, round($rewardAllocated - $rewardSpent, 2));
        $cashRefund = max(0, round($cashAllocated - $cashSpent, 2));

        DB::transaction(function () use ($ad, $rewardRefund, $cashRefund) {
            if($rewardRefund > 0) {
                $spendTransaction = AdRewardTransaction::where('ad_id', $ad->id)
                    ->where('transaction_type', 'spend')
                    ->where('direction', 'outgoing')
                    ->latest('id')
                    ->first();

                $account = $spendTransaction?->account()->lockForUpdate()->first();
                $user = $ad->user()->first();

                if($account && $user && $account->period_month === $this->currentPeriod() && $this->isEligible($user) && $account->status === 'active') {
                    $account->update([
                        'available_amount' => round(((float) $account->available_amount + $rewardRefund), 2),
                        'used_amount' => max(0, round(((float) $account->used_amount - $rewardRefund), 2)),
                    ]);

                    $this->recordRewardTransaction($account, [
                        'user_id' => $account->user_id,
                        'ad_id' => $ad->id,
                        'period_month' => $account->period_month,
                        'amount' => $rewardRefund,
                        'transaction_type' => 'refund',
                        'direction' => 'incoming',
                        'metadata' => [
                            'source' => ['name' => config('ads.name')],
                            'reason' => 'unused_ad_budget',
                        ],
                    ]);
                }
            }

            if($cashRefund > 0) {
                $user = $ad->user()->with('wallet')->first();

                if($user?->wallet) {
                    $wallet = Wallet::whereKey($user->wallet->id)->lockForUpdate()->first();
                    $wallet->update([
                        'balance' => round(((float) $wallet->balance->getAmount() + $cashRefund), 2),
                    ]);

                    $wallet->transactions()->create([
                        'amount' => $cashRefund,
                        'transaction_type' => TransactionType::REFUND,
                        'status' => TransactionStatus::COMPLETED,
                        'direction' => TransactionDirection::INCOMING,
                        'currency' => $wallet->currency,
                        'metadata' => [
                            'ad_id' => $ad->id,
                            'source' => ['name' => config('ads.name')],
                            'reason' => 'unused_ad_budget',
                            'funding_source' => 'cash_balance',
                            'total_budget' => (float) $ad->total_budget,
                            'spent_budget' => (float) $ad->spent_budget,
                        ]
                    ]);
                }
            }
        });

        return [
            'reward' => $rewardRefund,
            'cash' => $cashRefund,
            'total' => round($rewardRefund + $cashRefund, 2),
        ];
    }

    public function pauseRewardOnlyAdsWithoutCash(User $user): void
    {
        $user->loadMissing('wallet');

        if((float) $user->wallet->balance->getAmount() > 0) {
            return;
        }

        $user->advertising()
            ->published()
            ->whereColumn('spent_budget', '<', 'total_budget')
            ->where(function($query) {
                $query->whereNull('funding_metadata')
                    ->orWhere('funding_metadata->cash_amount', 0);
            })
            ->update([
                'status' => AdStatus::PAUSED->value,
                'pause_reason' => 'reward_unavailable',
            ]);
    }

    private function currentAccount(User $user, bool $create, bool $lock = false): ?AdRewardAccount
    {
        $query = AdRewardAccount::where('user_id', $user->id)
            ->where('period_month', $this->currentPeriod());

        if($lock) {
            $query->lockForUpdate();
        }

        $account = $query->first();

        if(empty($account) && $create) {
            return $this->grantCurrentMonth($user);
        }

        return $account;
    }

    private function recordRewardTransaction(AdRewardAccount $account, array $data): AdRewardTransaction
    {
        $data['ad_reward_account_id'] = $account->id;
        $data['status'] = $data['status'] ?? 'completed';
        $data['metadata'] = $data['metadata'] ?? [];

        return AdRewardTransaction::create($data);
    }

    private function moneyData(float $amount, string $currency): array
    {
        return [
            'raw' => round($amount, 2),
            'formatted' => Num::currency($amount, $currency),
        ];
    }

    private function rewardAllocatedForAd(Ad $ad): float
    {
        return round((float) AdRewardTransaction::where('ad_id', $ad->id)
            ->where('transaction_type', 'spend')
            ->where('direction', 'outgoing')
            ->sum('amount'), 2);
    }

    private function cashAllocatedForAd(Ad $ad): float
    {
        $wallet = $ad->user()->with('wallet')->first()?->wallet;

        if(empty($wallet)) {
            return 0;
        }

        $allocatedAmount = round((float) $wallet->transactions()
            ->where('transaction_type', TransactionType::ADVERTISING->value)
            ->where('direction', TransactionDirection::OUTGOING->value)
            ->get()
            ->filter(fn($transaction) => (int) data_get($transaction->metadata, 'ad_id') === (int) $ad->id)
            ->sum('amount'), 2);

        if($allocatedAmount <= 0 && empty($ad->funding_metadata)) {
            return round((float) $ad->total_budget, 2);
        }

        return $allocatedAmount;
    }
}
