<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Business\Wallet;

use App\Http\Controllers\Controller;
use App\Services\Ad\AdRewardService;
use Illuminate\Http\RedirectResponse;

class WalletController extends Controller
{
    public function index()
    {
        $cashouts = me()->cashouts()->latest()->paginate(10);

        return view('business::wallet.overview.index', [
            'walletData' => me()->wallet,
            'walletSummary' => app(AdRewardService::class)->getWalletSummary(me()),
            'rewardProgress' => app(AdRewardService::class)->getRewardProgress(me()),
            'cashouts' => $cashouts
        ]);
    }

    public function createCashout()
    {
        return view('business::wallet.cashout.create');
    }

    public function claimReward(AdRewardService $rewardService): RedirectResponse
    {
        try {
            $rewardService->claimCurrentReward(me());
            return redirect()->route('business.wallet.index')->with('flashMessage', 'Monthly reward claimed successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('business.wallet.index')->with('errorMessage', $e->getMessage());
        }
    }
}
