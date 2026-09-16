<?php

namespace App\Actions\Ad;

use App\Models\Ad;
use App\Services\Ad\AdRewardService;

class RefundUnusedAdBudgetAction
{
    public function __construct(private Ad $adData)
    {
    }

    public function execute(): float
    {
        return (float) app(AdRewardService::class)->refundUnusedAdBudget($this->adData)['total'];
    }
}
