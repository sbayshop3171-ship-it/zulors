<?php

namespace App\Services\Feedback;

use Exception;

class ReportService
{
	private string $locale;

	private string $reportType;

	public function __construct(string $reportType)
	{
		$this->locale = app()->getLocale();

		$this->reportType = $reportType;
	}

	public function getReasons(): array
	{
        $reasons = var_path("world/reports/{$this->reportType}/{$this->locale}.php");

        if(! file_exists($reasons)) {
            throw new Exception('An error occurred while fetching report reasons.');
        }

        return require $reasons;
	}
}
