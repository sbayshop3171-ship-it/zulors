<?php

namespace App\Livewire\User\Onboarding;

use App\Services\World\WorldService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Livewire\User\Onboarding\Base\OnboardingBase;

class Country extends OnboardingBase
{
    public string | null $country;
    public array $worldCountries;
	public int $stepIndex;
    public array $stepData;

    public function mount(WorldService $world)
    {
        $this->worldCountries = collect($world->countries())->map(function($value, $key) {
            return [
                'key' => $key,
                'value' => $value,
            ];
        })->values()->toArray();

        $this->country = me()->country;
    }

    public function render()
    {
        return view('livewire.user.onboarding.country');
    }

    public function saveSelectOption(string $action, $value)
    {
        $this->$action($value);
    }

    public function updatedCountry(string $value)
    {
        $countryKeys = collect($this->worldCountries)->map(function($item) {
            return $item['key'];
        })->toArray();

        $validator = Validator::make(['country' => $value], [
            'country' => ['required', 'string', 'size:2', 'uppercase', Rule::in($countryKeys)]
        ]);

        if ($validator->fails()) {
            $this->addError('country', __('validation.enum', ['attribute' => __('labels.country')]));
        }

        else{
            me()->update([
                'country' => $value
            ]);
        }
    }

    public function submitForm()
    {
        if(empty(me()->country)) {
            $this->addError('country', __('validation.select_or_skip', ['attribute' => __('labels.country')]));
        }

        else{
            $this->redirect($this->getNextStepRoute($this->stepIndex));
        }
    }

    public function skipStep()
    {
        if($this->stepData['is_skippable']) {
            if(empty(me()->country)) {
                me()->update(['country' => config('user.country')]);
            }
    
            $this->redirect($this->getNextStepRoute($this->stepIndex));
        }
    }
}
