<?php

namespace App\Actions\JobListing;

use App\Models\JobListing;

class DeleteJobAction
{
	private JobListing $jobData;

	public function __construct(JobListing $jobData) {
		$this->jobData = $jobData;
	}
	
	public function execute() {
		$this->jobData->delete();
	}
}
