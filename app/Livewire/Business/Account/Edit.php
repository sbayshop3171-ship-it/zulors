<?php

namespace App\Livewire\Business\Account;

use App\Rules\X\XRule;
use Livewire\Component;
use Illuminate\Validation\Rule;

class Edit extends Component
{
    public array $formData = [];

    public array $countries = [];

    public function mount()
    {
        $accountData = me()->businessAccount;

        $this->countries = world_countries();

        $this->formData = [
            'name' => $accountData->name,
            'description' => $accountData->description,
            'business_email' => $accountData->business_email,
            'business_phone' => $accountData->business_phone,
            'country' => $accountData->country,
            'city' => $accountData->city,
            'state' => $accountData->state,
            'address_line1' => $accountData->address_line1,
            'address_line2' => $accountData->address_line2,
            'postal_code' => $accountData->postal_code,
            'tax_number' => $accountData->tax_number,
            'billing_address' => $accountData->billing_address,
            'website' => $accountData->website
        ];
    }

    public function getRules()
    {
        $rules = [
            'formData.name' => [
                'required',
                'string',
                XRule::join('max', config('business.validation.name.max')),
            ],
            'formData.description' => [
                'nullable',
                'string',
                XRule::join('max', config('business.validation.description.max')),
            ],
            'formData.business_email' => [
                'required',
                'email',
                XRule::join('max', 120),
            ],
            'formData.business_phone' => [
                'required',
                'string',
                XRule::join('max', 120),
            ],
            'formData.country' => [
                'required',
                'string',
                Rule::in(array_column($this->countries, 'key')),
            ],
            'formData.city' => [
                'required',
                'string',
                XRule::join('max', config('business.validation.city.max')),
            ],
            'formData.state' => [
                'nullable',
                'string',
                XRule::join('max', config('business.validation.state.max')),
            ],
            'formData.address_line1' => [
                'required',
                'string',
                XRule::join('max', config('business.validation.address_line1.max')),
            ],
            'formData.address_line2' => [
                'nullable',
                'string',
                XRule::join('max', config('business.validation.address_line2.max')),
            ],
            'formData.postal_code' => [
                'required',
                'string',
                XRule::join('max', config('business.validation.postal_code.max')),
            ],
            'formData.tax_number' => [
                'required',
                'string',
                XRule::join('max', config('business.validation.tax_number.max')),
            ],
            'formData.billing_address' => [
                'required',
                'string',
                XRule::join('max', config('business.validation.billing_address.max')),
            ],
            'formData.website' => [
                'nullable',
                'string',
                XRule::join('max', config('business.validation.website.max')),
            ]
        ];

        return $rules;
    }

    public function render()
    {
        return view('livewire.business.account.edit');
    }

    public function submitForm()
    {
        $this->validate(rules: $this->getRules(), attributes: [
            'formData.name' => __('business/settings.form.name'),
            'formData.description' => __('business/settings.form.description'),
            'formData.business_email' => __('business/settings.form.business_email'),
            'formData.business_phone' => __('business/settings.form.business_phone'),
            'formData.country' => __('business/settings.form.country'),
            'formData.city' => __('business/settings.form.city'),
            'formData.state' => __('business/settings.form.state'),
            'formData.address_line1' => __('business/settings.form.address_line1'),
            'formData.address_line2' => __('business/settings.form.address_line2'),
            'formData.postal_code' => __('business/settings.form.postal_code'),
            'formData.tax_number' => __('business/settings.form.tax_number'),
            'formData.billing_address' => __('business/settings.form.billing_address'),
            'formData.website' => __('business/settings.form.website'),
        ]);

        me()->businessAccount->update([
            'name' => $this->formData['name'],
            'description' => $this->formData['description'],
            'business_email' => $this->formData['business_email'],
            'business_phone' => $this->formData['business_phone'],
            'country' => $this->formData['country'],
            'city' => $this->formData['city'],
            'state' => $this->formData['state'],
            'address_line1' => $this->formData['address_line1'],
            'address_line2' => $this->formData['address_line2'],
            'postal_code' => $this->formData['postal_code'],
            'tax_number' => $this->formData['tax_number'],
            'billing_address' => $this->formData['billing_address'],
            'website' => $this->formData['website'],
            'updated_at' => now(),
            'is_reviewed' => false,
            'verified' => false
        ]);

        return redirect()->route('business.settings.index');
    }
}
