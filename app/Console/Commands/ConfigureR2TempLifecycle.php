<?php

namespace App\Console\Commands;

use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Console\Command;

class ConfigureR2TempLifecycle extends Command
{
    protected $signature = 'media:configure-r2-temp-lifecycle {--days=3} {--apply : Apply the rules to the dedicated temp bucket}';
    protected $description = 'Preview or apply R2 raw-media expiry and incomplete multipart cleanup rules.';

    public function handle(R2DirectUploadService $r2): int
    {
        try {
            $rules = $r2->configureTempLifecycle((int) $this->option('days'), (bool) $this->option('apply'));
            $this->line(json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info($this->option('apply') ? 'Temp lifecycle rules applied.' : 'Preview only. Use --apply to save these rules.');
            return self::SUCCESS;
        }
        catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
