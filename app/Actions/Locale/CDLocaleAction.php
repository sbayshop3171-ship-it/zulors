<?php

namespace App\Actions\Locale;

use Exception;
use Illuminate\Support\Facades\File;

class CDLocaleAction
{
	/**
	 * We have implemented two methods to create and delete a locale.
	 * It's not recommended to have more than one action per Action class.
	 * This case is an exception. Delete and Create actions have the same logic.
	 * 
	 * (c) Mansur Terla. Zulors Developer.
	 */

	private string $localeCode;

	private array $translationPaths = [];

	private string $duplicatedPath;

	public function __construct(string $localeCode)
	{
		$this->localeCode = $localeCode;

		$this->translationPaths = [
			'folders' => [
				base_path('lang')
			],
			'php_files' => [
				var_path('world/countries'),
				var_path('world/reports/post'),
				var_path('world/reports/user'),
				var_path('world/user_fields'),
			],
		];
	}

	public function createLocale()
	{
		if ($this->translationFolderExists() || $this->translationFileExists()) {
			throw new Exception("Translation files already exist in {$this->duplicatedPath}.");
		}

		foreach ($this->translationPaths['folders'] as $folderPath) {
			File::copyDirectory("{$folderPath}/en", "{$folderPath}/{$this->localeCode}");
		}

		foreach ($this->translationPaths['php_files'] as $phpFilePath) {
			File::copy("{$phpFilePath}/en.php", "{$phpFilePath}/{$this->localeCode}.php");
		}
	}

	public function deleteLocale()
	{
		// Avoid deleting permanent locales.
		
		if (in_array($this->localeCode, config('localization.permanent_locales'))) {
			throw new Exception("Cannot delete permanent locale: {$this->localeCode}");

			return false;
		}

		foreach ($this->translationPaths['folders'] as $folderPath) {
			File::deleteDirectory("{$folderPath}/{$this->localeCode}");
		}

		foreach ($this->translationPaths['php_files'] as $phpFilePath) {
			File::delete("{$phpFilePath}/{$this->localeCode}.php");
		}
	}

	private function translationFolderExists(): bool
	{
		foreach ($this->translationPaths['folders'] as $folderPath) {
			if (File::exists("{$folderPath}/{$this->localeCode}")) {
				$this->duplicatedPath = "{$folderPath}/{$this->localeCode}";

				return true;
			}
		}

		return false;
	}

	private function translationFileExists(): bool
	{
		foreach ($this->translationPaths['php_files'] as $phpFilePath) {
			if (File::exists("{$phpFilePath}/{$this->localeCode}.php")) {
				$this->duplicatedPath = "{$phpFilePath}/{$this->localeCode}.php";

				return true;
			}
		}

		return false;
	}
}
