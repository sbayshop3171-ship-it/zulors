<?php

namespace App\Livewire\User\Onboarding;

use App\Livewire\User\Onboarding\Base\OnboardingBase;

class Profile extends OnboardingBase
{
    public array $microSteps;
    public array $birthDaysList;
    public array $birthMonthsList;
    public array $birthYearsList;
	public int $stepIndex;
    public array $stepData;

    public array $userData;
    public string | null $birthDay;
    public string | null $birthMonth;
    public string | null $birthYear;

    public function mount()
    {
        $this->microSteps = [
            'first_name' => false,
            'last_name' => false,
            'birthday' => false,
            'gender' => false
        ];

        $this->birthMonthsList = year_months();

        $this->birthYearsList = birth_years();

        $this->userData = [
            'first_name' => me()->first_name,
            'last_name' => me()->last_name,
            'gender' => me()->gender
        ];

        $this->birthMonth = me()->birth_month;
        $this->birthDay = me()->birth_day;
        $this->birthYear = me()->birth_year;

        $this->assignMonthDays();
    }

    public function saveSelectOption(string $action, $value)
    {
        $this->$action($value);
    }

    public function render()
    {
        return view('livewire.user.onboarding.profile');
    }

    public function submitForm()
    {
        if(empty($this->microSteps['first_name'])) {
            $this->saveFirstName();
        }

        else if(empty($this->microSteps['last_name'])) {
            $this->saveLastName();
        }

        else{
            if(empty(me()->birth_day) || empty(me()->birth_month) || empty(me()->birth_year)) {
                $this->addError('birthdateError', __('auth.birthdate_required'));
            }
            else{
                $this->redirect($this->getNextStepRoute($this->stepIndex));
            }
        }
    }

    private function saveFirstName()
    {
        $this->validate(rules: [
            'userData.first_name' => ['required', 'string', 'max:32']
        ], attributes: [
            'userData.first_name' => __('labels.name')
        ]);

        me()->update([
            'first_name' => $this->userData['first_name']
        ]);

        $this->microSteps['first_name'] = true;
    }

    private function saveLastName()
    {
        $rules = [
            'userData.last_name' => ['string', 'max:32']
        ];

        if((config('user.require.last_name') == true)) {
            array_push($rules['userData.last_name'], 'required');
        }

        $this->validate(rules: $rules, attributes: [
            'userData.last_name' => __('labels.last_name'),
        ]);

        me()->update([
            'last_name' => $this->userData['last_name']
        ]);

        $this->microSteps['last_name'] = true;
    }

    private function assignMonthDays()
    {
        if($this->birthMonth) {
            $this->birthDaysList = month_days($this->birthMonth, $this->birthYear ?? null);
        }
    }

    public function updatedBirthDay($value) {
        if(is_numeric($value)) {
            me()->update([
                'birth_day' => $value
            ]);
        }
    }

    public function updatedBirthMonth($value) {
        if(is_numeric($value) && in_array($value, ['01','02','03','04','05','06','07','08','09','10','11', '12'])) {
            me()->update([
                'birth_month' => $value
            ]);
            
            $this->assignMonthDays();
        }
    }

    public function updatedBirthYear($value) {
        if(is_numeric($value)) {
            me()->update([
                'birth_year' => $value
            ]);
        }
    }

    public function saveGender($value) {
        if(is_string($value) && in_array($value, ['male', 'female'])) {
            me()->update(['gender' => $value]);
        }
    }
}
