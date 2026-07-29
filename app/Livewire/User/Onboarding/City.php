<?php

namespace App\Livewire\User\Onboarding;

use App\Livewire\User\Onboarding\Base\OnboardingBase;

class City extends OnboardingBase
{
    public string | null $city;
	public int $stepIndex;
    public array $stepData;

    public function mount()
    {
        $this->city = me()->city;
    }

    public function render()
    {
        return view('livewire.user.onboarding.city');
    }

    public function submitForm()
    {
        $this->validate(rules: [
            'city' => ['required', 'string', 'max:32']
        ], attributes: [
            'city' => __('labels.city'),
        ]);

        me()->update([
            'city' => $this->city
        ]);

        $this->redirect($this->getNextStepRoute($this->stepIndex));
    }

    public function skipStep()
    {
        if($this->stepData['is_skippable']) {
            if(empty($this->city)) {
                me()->update([
                    'city' => config('user.city')
                ]);
            }

            $this->redirect($this->getNextStepRoute($this->stepIndex));
        }
    }
}
