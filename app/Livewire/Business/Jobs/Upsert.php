<?php

namespace App\Livewire\Business\Jobs;

use App\Rules\X\XRule;
use Livewire\Component;
use App\Models\Category;
use App\Enums\Job\JobType;
use App\Models\JobListing;
use App\Enums\Job\JobStatus;
use App\Enums\Job\JobApproval;
use Illuminate\Validation\Rule;
use App\Services\Currency\Fiat\FiatCurrencyService;

class Upsert extends Component
{
    public array $jobTypes;
    public array $currencies;
    public array $categories;
    public string $upsertType;
    public JobListing $jobData;
    public $formData = [];

    public function mount()
    {
        $fiatCurrencyService = app(FiatCurrencyService::class);

        $this->jobTypes = JobType::types();
        $this->currencies = $fiatCurrencyService->getPairedCurrencies()->toArray();
        $this->categories = Category::getJobCategories();
        $this->formData = [
            'title' => $this->jobData->title,
            'description' => $this->jobData->description,
            'overview' => $this->jobData->overview,
            'currency' => $this->jobData->currency,
            'category_id' => $this->jobData->category_id,
            'location' => $this->jobData->location,
            'is_urgent' => ($this->jobData->is_urgent) ? 'yes' : 'no',
            'type' => $this->jobData->type->value,
            'is_start_income' => ($this->jobData->is_start_income) ? 'yes' : 'no',
            'income' => $this->jobData->income,
        ];
    }

    public function render()
    {
        return view('livewire.business.jobs.upsert');
    }

    public function getRules()
    {
        $rules = [
            'formData.title' => [
                'required',
                'string', 
                XRule::join('max', config('jobs.job.validation.title.max')),
            ],
            'formData.overview' => [
                'required',
                'string',
                XRule::join('max', config('jobs.job.validation.overview.max')),
            ],
            'formData.description' => [
                'required',
                'string',
                XRule::join('max', config('jobs.job.validation.desc.max')),
            ],
            'formData.category_id' => ['required', 'integer', Rule::in(array_column($this->categories, 'key'))],
            'formData.currency' => ['required', 'string', Rule::in(array_column($this->currencies, 'key'))],
            'formData.location' => ['nullable', 'string', XRule::join('max', config('jobs.job.validation.address.max'))],
            'formData.income' => ['required', 'numeric', 'min:0.1'],
            'formData.type' => ['required', 'string', Rule::in(array_column($this->jobTypes, 'key'))],
            'formData.is_start_income' => ['required', 'string', Rule::in(['yes', 'no'])],
            'formData.is_urgent' => ['required', 'string', Rule::in(['yes', 'no'])],
        ];

        return $rules;
    }

    public function submitForm()
    {
        $this->validate(rules: $this->getRules(), attributes: [
            'formData.title' => __('business/jobs.form.title'),
            'formData.description' => __('business/jobs.form.desc'),
            'formData.overview' => __('business/jobs.form.overview'),
            'formData.currency' => __('business/jobs.form.currency'),
            'formData.category_id' => __('business/jobs.form.category'),
            'formData.location' => __('business/jobs.form.address'),
            'formData.is_urgent' => __('business/jobs.form.urgency'),
            'formData.type' => __('business/jobs.form.job_type'),
            'formData.is_start_income' => __('business/jobs.form.income_range'),
            'formData.income' => __('business/jobs.form.income'),
        ]);

        $upsertData = [
            'title' => e($this->formData['title']),
            'description' => e($this->formData['description']),
            'overview' => e($this->formData['overview']),
            'currency' => $this->formData['currency'],
            'category_id' => $this->formData['category_id'],
            'location' => $this->formData['location'],
            'is_remote' => (empty($this->formData['location'])) ? true : false,
            'is_urgent' => ($this->formData['is_urgent'] == 'yes') ? true : false,
            'type' => JobType::from($this->formData['type']),
            'is_start_income' => ($this->formData['is_start_income'] == 'yes') ? true : false,
            'income' => $this->formData['income'],
        ];

        if($this->upsertType == 'create') {
            // Never change status on update. Add once on create.
            // User can change it from overview page.

            $upsertData['status'] = JobStatus::ACTIVE;
        } 
        else {
            // If the job is rejected, set it to pending.
            // This is to allow the user to update the job and resubmit it for approval.

            if($this->jobData->approval->isRejected()) {
                $upsertData['approval'] = JobApproval::PENDING;
            }
        }

        $this->jobData->update($upsertData);
        
        return redirect()->route('business.jobs.index');
    }
}
