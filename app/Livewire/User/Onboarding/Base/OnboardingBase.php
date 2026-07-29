<?php

namespace App\Livewire\User\Onboarding\Base;

use Livewire\Component;

abstract class OnboardingBase extends Component
{
	protected function getNextStepRoute(int $stepIndex): string
	{
		$stepsMap = array_keys(config('user.onboarding_steps'));

		$nextStepName = isset($stepsMap[$stepIndex + 1]) ? $stepsMap[$stepIndex + 1] : null;

		if ($nextStepName) {
			return route('user.onboarding.index', $nextStepName);
		}

		return route('user.desktop.index');
	}
}
