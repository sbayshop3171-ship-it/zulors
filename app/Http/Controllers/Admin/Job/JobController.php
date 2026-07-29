<?php

namespace App\Http\Controllers\Admin\Job;

use App\Models\JobListing;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Enums\Job\JobApproval;
use App\Http\Controllers\Controller;
use App\Actions\JobListing\DeleteJobAction;
use App\Notifications\User\Job\JobApprovedNotification;
use App\Notifications\User\Job\JobRejectedNotification;

class JobController extends Controller
{
    private $filters = [];

    public function index(Request $request)
    {
        $approval = $request->string('approval')->value;
        $approval = in_array($approval, array_column(JobApproval::cases(), 'value')) ? $approval : 'all';

        $this->filters = [
            'search' => $request->string('search')->value,
            'approval' => $approval,
        ];

        $jobs = JobListing::excludeDraft()->when(($this->filters['approval'] != 'all'), function($query) {
            $query->where('approval', JobApproval::tryFrom($this->filters['approval']));
        })->when((! empty($this->filters['search'])), function ($query) {
            $query->where(function($query) {
                $query->where('title', 'like', "%{$this->filters['search']}%")
                    ->orWhere('overview', 'like', "%{$this->filters['search']}%")
                    ->orWhere('location', 'like', "%{$this->filters['search']}%")
                    ->orWhere('description', 'like', "%{$this->filters['search']}%");
            });
        })->with(['user', 'category'])->latest('id')->paginate(10);

        return view('admin::jobs.index.index', [
            'jobs' => $jobs,
            'filters' => $this->filters
        ]);
    }

    public function show(Request $request, int $jobId)
    {
        $jobData = JobListing::excludeDraft()->with(['user', 'category'])->findOrFail($jobId);

        return view('admin::jobs.show.index', [
            'jobData' => $jobData
        ]);
    }

    public function approve(Request $request, int $jobId)
    {
        $jobData = JobListing::excludeDraft()->findOrFail($jobId);
        $shouldNotify = ! $jobData->approval->isApproved();

        $jobData->update(['approval' => JobApproval::APPROVED]);

        if($shouldNotify) {
            $jobData->user->notify(new JobApprovedNotification($jobData));
        }

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.jobs.approved_success')))->get());
    }

    public function reject(Request $request, int $jobId)
    {
        $jobData = JobListing::excludeDraft()->findOrFail($jobId);
        $shouldNotify = ! $jobData->approval->isRejected();

        $jobData->update(['approval' => JobApproval::REJECTED]);

        if($shouldNotify) {
            $jobData->user->notify(new JobRejectedNotification($jobData));
        }

        return back()->with('flashMessage', (new Flash(content: __('admin/flash.jobs.rejected_success')))->get());
    }

    public function destroy(int $jobId)
    {
        $jobData = JobListing::excludeDraft()->findOrFail($jobId);

        (new DeleteJobAction($jobData))->execute();

        return redirect()->route('admin.jobs.index')->with('flashMessage', (new Flash(content: __('admin/flash.jobs.deleted_success')))->get());
    }
}
