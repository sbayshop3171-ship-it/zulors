<?php

namespace App\Console\Commands;

use App\Jobs\Media\CleanupPublication;
use App\Jobs\Media\DeliverPublicationEvent;
use App\Jobs\Media\FinalizePublication;
use App\Jobs\Media\ProcessPublicationItem;
use App\Models\MediaPublication;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReconcileMediaPublications extends Command
{
    protected $signature = 'media:reconcile-publications {--limit=50}';
    protected $description = 'Recover publication dispatches and expire abandoned upload intents.';

    public function handle(): int
    {
        if(! Schema::hasTable('media_publications')) return self::SUCCESS;
        $publications = MediaPublication::whereNotIn('status', ['published', 'cancelled'])
            ->where(fn($q) => $q->where('expires_at', '<=', now())->orWhere('status', '!=', 'failed'))
            ->oldest('updated_at')->limit(max(1, min(200, (int) $this->option('limit'))))->get();
        foreach($publications as $publication) {
            DB::transaction(function () use ($publication) {
                $publication = MediaPublication::whereKey($publication->id)->lockForUpdate()->first();
                if($publication->terminal()) return;
                if($publication->expires_at->isPast()) {
                    $publication->update(['status' => 'cancelled', 'error' => 'Upload expired. Start a new publication.']);
                    CleanupPublication::dispatch($publication->id)->afterCommit();
                    return;
                }
                if($publication->status === 'failed') return;
                foreach($publication->items as $item) {
                    $staleAfter = $item->status === 'processing' ? (int) config('media.publications.timeout') + 120 : 300;
                    if(in_array($item->status, ['uploaded', 'processing'], true)
                        && (! $item->dispatched_at || $item->dispatched_at->lt(now()->subSeconds($staleAfter)))) {
                        $item->update(['dispatched_at' => now()]);
                        ProcessPublicationItem::dispatch($item->id, $item->generation)->afterCommit();
                    }
                }
                if($publication->items->every(fn($item) => $item->status === 'processed')) {
                    FinalizePublication::dispatch($publication->id)->afterCommit();
                }
                // Rotate bounded scans without resetting the immutable item queue age.
                $publication->touch();
            });
        }
        MediaPublication::whereIn('status', ['published', 'cancelled'])->where(function ($q) {
            $q->whereHas('items', fn($items) => $items->whereNull('original_deleted_at')->whereNotNull('upload'))
                ->orWhere(fn($cancelled) => $cancelled->where('status', 'cancelled')->whereHas('items', fn($items) => $items->whereNull('outputs_deleted_at')));
        })
            ->oldest('updated_at')->limit(50)->get()->each(function ($p) {
                CleanupPublication::dispatch($p->id);
                $p->touch();
            });
        DeliverPublicationEvent::dispatchPending(50);
        return self::SUCCESS;
    }
}
