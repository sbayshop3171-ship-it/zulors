<?php

namespace App\Services\Ad;

use App\Enums\Ad\AdStatus;
use App\Enums\Wallet\TransactionDirection;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionType;
use App\Models\Ad;
use App\Models\AdRewardAccount;
use App\Models\AdRewardTransaction;
use App\Models\Post;
use App\Models\User;
use App\Models\Wallet;
use App\Enums\Post\PostStatus;
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

    public function currentCycleKey(User $user): string
    {
        $createdAt = $user->created_at ?: now();
        $months = max(0, $createdAt->diffInMonths(now(), false));

        return $createdAt->copy()->addMonthsNoOverflow($months)->format('Y-m-d');
    }

    public function getRewardProgress(User $user): array
    {
        $user->loadMissing('wallet');
        $requiredPosts = 7;
        $cycleKey = $this->currentCycleKey($user);
        $cycleStart = $user->created_at->copy()->addMonthsNoOverflow(max(0, $user->created_at->diffInMonths(now(), false)));
        $accountAgeDays = max(0, $user->created_at->startOfDay()->diffInDays(now()->startOfDay()));
        $posts = Post::where('user_id', $user->id)
            ->whereIn('status', [PostStatus::ACTIVE->value, PostStatus::PUBLISHED->value])
            ->whereBetween('created_at', [$cycleStart, now()])
            ->count();
        $account = AdRewardAccount::where('user_id', $user->id)
            ->where('cycle_key', $cycleKey)
            ->first();
        $initialAgeMet = $user->created_at->lte(now()->subDays(7));
        $eligible = $this->isEligible($user) && $initialAgeMet && $posts >= $requiredPosts;
        $claimed = (bool) $account?->claimed_at;

        return [
            'cycle_key' => $cycleKey,
            'required_posts' => $requiredPosts,
            'posts_completed' => min($posts, $requiredPosts),
            'account_age_days' => min($accountAgeDays, 7),
            'account_age_required' => 7,
            'account_age_met' => $initialAgeMet,
            'email_verified' => (bool) $user->verified,
            'eligible' => $eligible,
            'claimed' => $claimed,
            'claimed_at' => $account?->claimed_at?->toIso8601String(),
            'status' => $claimed ? 'claimed' : ($eligible ? 'ready' : 'locked'),
            'reward_amount' => $this->monthlyAmount(),
        ];
    }

    public function claimCurrentReward(User $user): AdRewardAccount
    {
        return DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $progress = $this->getRewardProgress($user);
            if (! $progress['eligible']) {
                throw new \RuntimeException('Complete email verification, account age, and monthly post requirements first.');
            }

            $account = AdRewardAccount::where('user_id', $user->id)
                ->where('cycle_key', $progress['cycle_key'])
                ->lockForUpdate()->first();
            if ($account?->claimed_at) {
                return $account;
            }

            $account ??= AdRewardAccount::create([
                'user_id' => $user->id,
                'period_month' => now()->format('Y-m'),
                'cycle_key' => $progress['cycle_key'],
                'monthly_amount' => $this->monthlyAmount(),
                'available_amount' => $this->monthlyAmount(),
                'used_amount' => 0,
                'status' => 'active',
                'expires_at' => null,
                'claimed_at' => now(),
            ]);
            if ($account->wasRecentlyCreated) {
                $this->recordRewardTransaction($account, [
                    'user_id' => $user->id,
                    'period_month' => $account->period_month,
                    'amount' => $account->monthly_amount,
                    'transaction_type' => 'claim',
                    'direction' => 'incoming',
                    'metadata' => ['source' => ['name' => config('ads.name')], 'reason' => 'monthly_activity_reward'],
                ]);
            }

            return $account;
        });
    }

    public function isEligible(User $user): bool
    {
        if (! $this->isEnabled() || ! $user->isVerified()) {
            return false;
        }

        $phone = preg_replace('/[^0-9+]/', '', (string) $user->phone);
        if ($phone !== '' && User::where('id', '!=', $user->id)->where('verified', true)
            ->where('phone', $user->phone)->exists()) {
            return false;
        }

        return true;
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

        return round((float) AdRewardAccount::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('claimed_at')
            ->sum('available_amount'), 2);
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
                'cycle_key' => $this->currentCycleKey($user),
                'monthly_amount' => $amount,
                'available_amount' => $amount,
                'used_amount' => 0,
                'status' => 'active',
                'expires_at' => now()->endOfMonth(),
                'claimed_at' => now(),
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

    public function grantCurrentMonthToEligibleUsers(): array
    {
        $period = $this->currentPeriod();
        $stats = [
            'eligible' => 0,
            'granted' => 0,
            'already_granted' => 0,
        ];

        if(! $this->isEnabled()) {
            return $stats;
        }

        User::where('verified', true)->chunkById(200, function($users) use (&$stats, $period) {
            foreach($users as $user) {
                $stats['eligible']++;

                $alreadyGranted = AdRewardAccount::where('user_id', $user->id)
                    ->where('period_month', $period)
                    ->exists();

                if($this->grantCurrentMonth($user)) {
                    if($alreadyGranted) {
                        $stats['already_granted']++;
                    }
                    else {
                        $stats['granted']++;
                    }
                }
            }
        });

        return $stats;
    }

    public function resetMonthlyCredits(): int
    {
        $currentPeriod = $this->currentPeriod();
        $processed = 0;

        AdRewardAccount::where('period_month', '!=', $currentPeriod)
            ->whereNull('claimed_at')
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

        return $processed;
    }

    public function allocateAdBudget(User $user, Ad $ad, float $amount, float $pricePerView): array
    {
        return DB::transaction(function () use ($user, $ad, $amount, $pricePerView) {
            $amount = round($amount, 2);
            $user = User::whereKey($user->id)->firstOrFail();
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $rewardAccounts = $this->isEligible($user)
                ? AdRewardAccount::where('user_id', $user->id)->where('status', 'active')->whereNotNull('claimed_at')->orderBy('id')->lockForUpdate()->get()
                : collect();
            $rewardAvailable = round((float) $rewardAccounts->sum('available_amount'), 2);
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

            if($rewardAmount > 0) {
                $remainingReward = $rewardAmount;
                foreach($rewardAccounts as $account) {
                    if($remainingReward <= 0) break;
                    $accountReward = min($remainingReward, (float) $account->available_amount);
                    $account->update([
                        'available_amount' => round(((float) $account->available_amount - $accountReward), 2),
                        'used_amount' => round(((float) $account->used_amount + $accountReward), 2),
                    ]);
                    $this->recordRewardTransaction($account, [
                        'user_id' => $user->id,
                        'ad_id' => $ad->id,
                        'period_month' => $account->period_month,
                        'amount' => $accountReward,
                        'transaction_type' => 'spend',
                        'direction' => 'outgoing',
                        'metadata' => ['source' => ['name' => config('ads.name')], 'reason' => 'ad_budget_allocation', 'price_per_view' => (float) $pricePerView],
                    ]);
                    $remainingReward = round($remainingReward - $accountReward, 2);
                }
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

    public function fundAdForDelivery(Ad $ad): array
    {
        if(! empty($ad->funding_metadata)) {
            return [
                'success' => true,
                'metadata' => $ad->funding_metadata,
            ];
        }

        if($this->shouldTreatAdAsLegacyCashFunded($ad)) {
            return [
                'success' => true,
                'metadata' => null,
                'legacy_funded' => true,
            ];
        }

        $amount = max(0, round(((float) $ad->total_budget - (float) $ad->spent_budget), 2));

        if($amount <= 0) {
            return [
                'success' => true,
                'metadata' => [
                    'reward_amount' => 0,
                    'cash_amount' => 0,
                    'total_amount' => 0,
                    'price_per_view' => (float) $ad->price_per_view,
                ],
            ];
        }

        $user = $ad->user()->firstOrFail();
        $allocation = $this->allocateAdBudget($user, $ad, $amount, (float) $ad->price_per_view);

        if(empty($allocation['success'])) {
            return [
                'success' => false,
                'available' => (float) ($allocation['available'] ?? 0),
                'required' => $amount,
            ];
        }

        return [
            'success' => true,
            'metadata' => [
                'reward_amount' => (float) ($allocation['reward_amount'] ?? 0),
                'cash_amount' => (float) ($allocation['cash_amount'] ?? 0),
                'total_amount' => $amount,
                'price_per_view' => (float) $ad->price_per_view,
            ],
        ];
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
            ->where(function ($query) use ($user) {
                $query->where('cycle_key', $this->currentCycleKey($user))
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('cycle_key')->where('period_month', $this->currentPeriod());
                    });
            });

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

        if($allocatedAmount <= 0 && empty($ad->funding_metadata) && $this->shouldTreatAdAsLegacyCashFunded($ad)) {
            return round((float) $ad->total_budget, 2);
        }

        return $allocatedAmount;
    }

    private function shouldTreatAdAsLegacyCashFunded(Ad $ad): bool
    {
        if($ad->approval->isPending()) {
            return false;
        }

        if(in_array($ad->pause_reason, ['insufficient_funds', 'reward_unavailable'], true)) {
            return false;
        }

        return $ad->approval->isApproved();
    }
}
