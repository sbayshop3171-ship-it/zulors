<?php

namespace App\Console\Commands\Calls;

use App\Services\Calls\StaleCallCleanupService;
use Illuminate\Console\Command;

class CleanupStaleCalls extends Command
{
    protected $signature = 'calls:cleanup-stale {--limit=100 : Maximum stale calls to finalize in one run}';

    protected $description = 'Finalize expired or abandoned active call sessions.';

    public function handle(StaleCallCleanupService $staleCalls): int
    {
        $cleanedCount = $staleCalls->cleanup(limit: max(1, (int) $this->option('limit')));

        $this->info("Cleaned {$cleanedCount} stale call(s).");

        return self::SUCCESS;
    }
}
