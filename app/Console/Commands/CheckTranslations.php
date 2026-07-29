<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckTranslations extends Command
{
    protected $signature = 'translations:check {--master=en}';
    protected $description = 'Check for missing translation keys across locales';

    public function handle()
    {
        $masterLocale = $this->option('master');
        $masterPath = lang_path($masterLocale);
        
        if (!File::isDirectory($masterPath)) {
            $this->error("Master locale directory '{$masterLocale}' not found!");
            return 1;
        }

        // Get all locale directories
        $localeDirectories = File::directories(lang_path());
        $locales = collect($localeDirectories)
            ->map(fn($path) => basename($path))
            ->reject(fn($locale) => $locale === $masterLocale)
            ->values();

        if ($locales->isEmpty()) {
            $this->info('No other locales found to compare.');
            return 0;
        }

        $this->info("Checking translations against master locale: {$masterLocale}");
        $this->newLine();

        // Get all PHP files from master locale
        $masterFiles = $this->getPhpFiles($masterPath);
        $hasIssues = false;

        foreach ($masterFiles as $file) {
            $relativePath = str_replace($masterPath . '/', '', $file);
            $masterKeys = $this->getKeysFromFile($file);
            
            $this->line("Checking: {$relativePath}");

            foreach ($locales as $locale) {
                $localeFile = lang_path($locale . '/' . $relativePath);
                
                if (!File::exists($localeFile)) {
                    $this->error("✗ {$locale}: File missing");
                    $hasIssues = true;
                    continue;
                }

                $localeKeys = $this->getKeysFromFile($localeFile);
                $missingKeys = array_diff($masterKeys, $localeKeys);
                $extraKeys = array_diff($localeKeys, $masterKeys);

                if (!empty($missingKeys)) {
                    $this->error("✗ {$locale}: Missing keys: " . implode(', ', $missingKeys));
                    $hasIssues = true;
                }

                if (!empty($extraKeys)) {
                    $this->warn("! {$locale}: Extra keys: " . implode(', ', $extraKeys));
                }

                if (empty($missingKeys) && empty($extraKeys)) {
                    $this->info("✓ {$locale}: OK");
                }
            }
            
            $this->newLine();
        }

        // Check for missing directories in other locales
        $masterSubdirs = collect(File::directories($masterPath))->map(fn($path) => basename($path));
        
        foreach ($locales as $locale) {
            $localeSubdirs = collect(File::directories(lang_path($locale)))->map(fn($path) => basename($path));
            $missingDirs = $masterSubdirs->diff($localeSubdirs);
            
            if ($missingDirs->isNotEmpty()) {
                $this->error("Locale {$locale} is missing directories: " . $missingDirs->implode(', '));
                $hasIssues = true;
            }
        }

        if (!$hasIssues) {
            $this->info('All translations are synchronized! ✓');
        } else {
            $this->error('Translation synchronization issues found!');
            return 1;
        }

        return 0;
    }

    private function getPhpFiles($directory)
    {
        $files = [];
        
        // Get PHP files in root directory
        $files = array_merge($files, File::glob($directory . '/*.php'));
        
        // Get PHP files in subdirectories (one level deep only)
        $subdirectories = File::directories($directory);
        foreach ($subdirectories as $subdir) {
            $subFiles = File::glob($subdir . '/*.php');
            $files = array_merge($files, $subFiles);
        }
        
        return $files;
    }

    private function getKeysFromFile($filePath)
    {
        $translations = include $filePath;
        return $this->flattenKeys($translations);
    }

    private function flattenKeys($array, $prefix = '')
    {
        $keys = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;
            
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $newKey));
            } else {
                $keys[] = $newKey;
            }
        }
        
        return $keys;
    }
}