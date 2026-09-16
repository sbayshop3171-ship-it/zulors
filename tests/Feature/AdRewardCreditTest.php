<?php

namespace Tests\Feature;

use App\Actions\Ad\DeleteAdAction;
use App\Database\Configs\Table;
use App\Enums\Ad\AdApproval;
use App\Enums\Ad\AdStatus;
use App\Enums\NotificationType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Ad;
use App\Models\User;
use App\Models\UserNotificationSettings;
use App\Services\Ad\AdRewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdRewardCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_wallet_summary_includes_campaign_only_reward_credit(): void
    {
        config([
            'wallet.ads_reward.enabled' => true,
            'wallet.ads_reward.monthly_amount' => 300,
        ]);

        $user = $this->createUser('reward-summary-user', verified: true);
        $this->createWallet($user, 550);

        $service = app(AdRewardService::class);
        $service->grantCurrentMonth($user);

        $summary = $service->getWalletSummary($user->refresh());

        $this->assertSame(850.0, $summary['balance']['raw']);
        $this->assertSame(550.0, $summary['cash_balance']['raw']);
        $this->assertSame(550.0, $summary['transferable_balance']['raw']);
        $this->assertSame(300.0, $summary['ads_reward_credit']['raw']);
        $this->assertTrue($summary['reward_eligible']);
    }

    public function test_unverified_user_does_not_receive_reward_credit(): void
    {
        config([
            'wallet.ads_reward.enabled' => true,
            'wallet.ads_reward.monthly_amount' => 300,
        ]);

        $user = $this->createUser('reward-unverified-user', verified: false);
        $this->createWallet($user, 550);

        $service = app(AdRewardService::class);

        $this->assertNull($service->grantCurrentMonth($user));
        $this->assertSame(0.0, $service->getWalletSummary($user)['ads_reward_credit']['raw']);
    }

    public function test_transfer_can_only_use_cash_balance_not_reward_credit(): void
    {
        config([
            'wallet.ads_reward.enabled' => true,
            'wallet.ads_reward.monthly_amount' => 300,
            'wallet.transfer.min_amount' => 10,
            'wallet.transfer.max_amount' => 1000000,
            'wallet.commission.transfer' => 1,
        ]);

        $sender = $this->createUser('reward-transfer-sender', verified: true);
        $receiver = $this->createUser('reward-transfer-receiver', verified: true);
        $this->createWallet($sender, 550);
        $this->createWallet($receiver, 0);

        app(AdRewardService::class)->grantCurrentMonth($sender);

        $this->actingAs($sender)
            ->withoutMiddleware()
            ->postJson('/api/wallet/transfer', [
                'wallet_number' => $receiver->wallet->wallet_number,
                'amount' => 551,
            ])
            ->assertStatus(422);

        $this->actingAs($sender)
            ->withoutMiddleware()
            ->postJson('/api/wallet/transfer', [
                'wallet_number' => $receiver->wallet->wallet_number,
                'amount' => 550,
            ])
            ->assertOk();
    }

    public function test_ad_budget_uses_reward_credit_before_cash_and_refunds_by_source(): void
    {
        config([
            'wallet.ads_reward.enabled' => true,
            'wallet.ads_reward.monthly_amount' => 300,
        ]);

        $user = $this->createUser('reward-ad-owner', verified: true);
        $this->createWallet($user, 550);
        $ad = $this->createAd($user, [
            'total_budget' => 400,
            'spent_budget' => 250,
        ]);

        $allocation = app(AdRewardService::class)->allocateAdBudget($user, $ad, 400, 0.01);

        $this->assertTrue($allocation['success']);
        $this->assertSame(300.0, $allocation['reward_amount']);
        $this->assertSame(100.0, $allocation['cash_amount']);
        $this->assertSame(450.0, $user->wallet->refresh()->balance->getAmount());
        $this->assertDatabaseHas(Table::AD_REWARD_ACCOUNTS, [
            'user_id' => $user->id,
            'available_amount' => 0,
        ]);

        (new DeleteAdAction($ad->refresh()))->execute();

        $this->assertSame(550.0, $user->wallet->refresh()->balance->getAmount());
        $this->assertDatabaseHas(Table::AD_REWARD_ACCOUNTS, [
            'user_id' => $user->id,
            'available_amount' => 50,
        ]);
    }

    private function createUser(string $username, bool $verified): User
    {
        $user = User::query()->create([
            'first_name' => 'Reward',
            'last_name' => 'User',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => "{$username}@example.com",
            'phone' => '',
            'website' => '',
            'bio' => '',
            'country' => null,
            'city' => null,
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'age' => null,
            'gender' => 'male',
            'last_active' => now()->timestamp,
            'language' => 'en',
            'avatar' => null,
            'cover' => null,
            'verified' => $verified,
            'verified_at' => $verified ? now() : null,
            'tips' => [],
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => UserRole::USER,
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => UserStatus::ACTIVE,
            'type' => UserType::AUTHOR,
        ]);

        UserNotificationSettings::query()->create([
            'user_id' => $user->id,
            'type' => NotificationType::EMAIL,
        ]);

        UserNotificationSettings::query()->create([
            'user_id' => $user->id,
            'type' => NotificationType::PUSH,
        ]);

        return $user;
    }

    private function createWallet(User $user, float $balance): void
    {
        $user->wallet()->create([
            'wallet_number' => 'ZLR-TEST-' . strtoupper($user->username),
            'balance' => $balance,
            'currency' => config('app.default_currency') ?: 'USD',
        ]);
    }

    private function createAd(User $user, array $overrides = []): Ad
    {
        return Ad::query()->create(array_merge([
            'user_id' => $user->id,
            'title' => 'Reward credit campaign',
            'content' => 'Reward credit campaign content for testing.',
            'cta_text' => 'Open',
            'status' => AdStatus::PUBLISHED,
            'type' => 'image',
            'total_budget' => 100,
            'spent_budget' => 0,
            'price_per_view' => 0.01,
            'target_url' => 'https://example.com',
            'target_topics' => ['tech'],
            'approval' => AdApproval::APPROVED,
            'views_count' => 0,
            'clicks_count' => 0,
        ], $overrides));
    }
}
