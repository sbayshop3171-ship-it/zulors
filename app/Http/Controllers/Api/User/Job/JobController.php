<?php

namespace App\Http\Controllers\Api\User\Job;

use App\Models\Category;
use App\Models\JobListing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Job\JobResource;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Job\JobCollection;
use App\Http\Resources\User\Market\CategoryCollection;
use App\Traits\Http\Controllers\Api\User\Jobs\ValidatesJobFilters;

class JobController extends Controller
{
    use SupportsApiResponses,
        ValidatesJobFilters;

    public function getCategories(Request $request)
    {
        $categories = Category::active()->jobs()->take(16)->get();

        return $this->responseSuccess([
            'data' => CategoryCollection::make($categories)
        ]);
    }

    public function getJobs(Request $request)
    {
        $filterOption = $this->getValidatedFilters($request);

        $jobs = JobListing::listable()->filter($filterOption)->withRelations()->latest('id')->take(12)->get();

        return $this->responseSuccess([
            'data' => JobCollection::make($jobs)
        ]);
    }

    public function getJobData($jobHashId)
    {
        $jobData = JobListing::active()->findByHashId($jobHashId);

        if($jobData) {
            // TODO: This is a temporary solution to allow the user to view the Job if it is rejected.
            // We need to improve this in the future.
            
            if(! $jobData->approval->isApproved()) {
                if(! me()->isRoot() && ! me()->id == $jobData->user_id) {
                    return $this->responseResourceNotFoundError('Job', $jobHashId);
                }
            }
    
            return $this->responseSuccess([
                'data' => JobResource::make($jobData)
            ]);
        }

        return $this->responseResourceNotFoundError('Job', $jobHashId);
    }

    public function bookmark(Request $request)
    {
        $jobId = $request->integer('id');

        $jobData = JobListing::listable()->find($jobId);

        if ($jobData) {
            $bookmarkedStatus = $jobData->isBookmarkedBy(me()->id);

            if($bookmarkedStatus) {
                $jobData->removeBookmark(me()->id);
            }
            else {
                $jobData->addBookmark(me()->id);
            }

            return $this->responseSuccess([
                'data' => [
                    'bookmarked' => (! $bookmarkedStatus)
                ]
            ]);
        }

        else{
            return $this->responseResourceNotFoundError('Job', $jobId);
        }
    }
}