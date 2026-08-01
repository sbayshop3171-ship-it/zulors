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

namespace App\Http\Controllers\Business\Job;

use App\Enums\Job\JobStatus;
use Illuminate\Http\Request;
use App\Support\Views\Flash;
use App\Http\Controllers\Controller;
use App\Actions\JobListing\DeleteJobAction;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->string('type', 'all');
        $type = in_array($type, ['all', 'active', 'archived']) ? $type : 'all';
        
        $jobsList = me()->jobListings()->with(['category', 'user'])->excludeDraft()->when($type, function($query) use ($type) {
            if($type == 'active') {
                $query->where('status', JobStatus::ACTIVE);
            }
            else if($type == 'archived') {
                $query->where('status', JobStatus::INACTIVE);
            }
        })->latest('id')->paginate(10);

        return view('business::jobs.index', [
            'type' => $type,
            'jobsList' => $jobsList
        ]);
    }

    public function edit($jobId)
    {
        $jobData = me()->jobListings()->where('id', $jobId)->firstOrFail();

        return view('business::jobs.edit', [
            'jobData' => $jobData
        ]);
    }

    public function show($jobId)
    {
        $jobData = me()->jobListings()->where('id', $jobId)->with(['category', 'user'])->firstOrFail();

        return view('business::jobs.show.index', [
            'jobData' => $jobData
        ]);
    }

    public function create()
    {
        return view('business::jobs.create', [
            'jobData' => $this->fetchOrInitializeDraftJob()
        ]);
    }

    private function fetchOrInitializeDraftJob()
    {
        $jobData = me()->jobListings()->where('status', JobStatus::DRAFT)->first();
    
        if(empty($jobData)) {
            me()->jobListings()->create([
                'currency' => $this->defaultCurrency(),
                'status' => JobStatus::DRAFT
            ]);

            return me()->jobListings()->where('status', JobStatus::DRAFT)->first();
        }

        else {
            return $jobData;
        }
    }

    private function defaultCurrency(): string
    {
        return config('app.default_currency') ?: 'USD';
    }

    public function destroy($jobId)
    {
        $jobData = me()->jobListings()->where('id', $jobId)->firstOrFail();

        (new DeleteJobAction($jobData))->execute();

        return redirect()->route('business.jobs.index')
            ->with('flashMessage', (new Flash(content: __('business/flash.jobs.job_deleted')))->get());
    }

    public function unpublish($jobId)
    {
        $jobData = me()->jobListings()->where('id', $jobId)->firstOrFail();

        $jobData->status = JobStatus::INACTIVE;
        $jobData->save();

        return redirect()->route('business.jobs.show', $jobId)
            ->with('flashMessage', (new Flash(content: __('business/flash.jobs.job_unpublished')))->get());
    }

    public function publish($jobId)
    {
        $jobData = me()->jobListings()->where('id', $jobId)->firstOrFail();

        $jobData->status = JobStatus::ACTIVE;
        $jobData->save();

        return redirect()->route('business.jobs.show', $jobId)
            ->with('flashMessage', (new Flash(content: __('business/flash.jobs.job_published')))->get());
    }
}
