<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCommentThreadRoots extends Command
{
    protected $signature = 'comments:backfill-thread-roots {--chunk=500 : Number of comment rows to process per batch}';

    protected $description = 'Backfill root comment IDs for existing comment threads';

    public function handle(): int
    {
        $chunkSize = max(1, min(2000, (int) $this->option('chunk')));

        if (! DB::table('comments')->whereNull('thread_root_id')->exists()) {
            $this->info('Comment thread roots are already complete.');

            return self::SUCCESS;
        }

        $this->info('Backfilling comment thread roots...');

        // Start from every actual root, including roots processed by an earlier partial run.
        DB::table('comments')->whereNull('parent_id')->orderBy('id')->chunkById($chunkSize, function ($roots) use ($chunkSize) {
            $frontier = [];
            $rootIds = [];

            foreach ($roots as $root) {
                $rootIds[] = (int) $root->id;
                $frontier[(int) $root->id] = ['root_id' => (int) $root->id, 'post_id' => (int) $root->post_id];
            }

            $this->setThreadRoots($rootIds, array_combine($rootIds, $rootIds));
            $this->propagate($frontier, $chunkSize);
        }, 'id');

        // Repair legacy rows whose parent is missing or belongs to another post.
        DB::table('comments as child')
            ->leftJoin('comments as parent', 'parent.id', '=', 'child.parent_id')
            ->whereNull('child.thread_root_id')
            ->whereNotNull('child.parent_id')
            ->where(function ($query) {
                $query->whereNull('parent.id')->orWhereColumn('parent.post_id', '<>', 'child.post_id');
            })
            ->select('child.id', 'child.post_id')
            ->orderBy('child.id')
            ->chunkById($chunkSize, function ($orphans) use ($chunkSize) {
                $frontier = [];
                $roots = [];

                foreach ($orphans as $orphan) {
                    $id = (int) $orphan->id;
                    $roots[$id] = $id;
                    $frontier[$id] = ['root_id' => $id, 'post_id' => (int) $orphan->post_id];
                }

                $ids = array_keys($roots);
                DB::table('comments')->whereIn('id', $ids)->update(['parent_id' => null]);
                $this->setThreadRoots($ids, $roots);
                $this->propagate($frontier, $chunkSize);
            }, 'child.id', 'id');

        // A valid comment tree always points to an older parent. This fallback handles
        // malformed cycles without letting one corrupt chain block the full backfill.
        while (DB::table('comments')->whereNull('thread_root_id')->exists()) {
            $candidate = DB::table('comments')->whereNull('thread_root_id')->orderBy('id')->first();
            if (! $candidate) {
                break;
            }

            $candidateId = (int) $candidate->id;
            $postId = (int) $candidate->post_id;
            $visited = [];
            $cursor = $candidate;
            $rootId = null;
            $cycleBreakId = null;

            while ($cursor && $cursor->parent_id !== null) {
                $cursorId = (int) $cursor->id;
                $visited[$cursorId] = true;
                $parent = DB::table('comments')->where('id', $cursor->parent_id)->first();

                if (! $parent || (int) $parent->post_id !== $postId) {
                    // Detach the oldest unresolved row; its subtree will be repaired below.
                    $rootId = $candidateId;
                    break;
                }

                $parentId = (int) $parent->id;
                if (isset($visited[$parentId])) {
                    $cycleBreakId = $cursorId;
                    $rootId = $cycleBreakId;
                    break;
                }

                if ($parent->thread_root_id !== null) {
                    $rootId = (int) $parent->thread_root_id;
                    break;
                }

                $cursor = $parent;
            }

            if ($rootId === null) {
                $rootId = $candidateId;
            }

            if ($cycleBreakId !== null || $rootId === $candidateId) {
                DB::table('comments')->where('id', $rootId)->update(['parent_id' => null]);
                $this->setThreadRoots([$rootId], [$rootId => $rootId]);
            } else {
                DB::table('comments')->where('id', $candidateId)->whereNull('thread_root_id')->update(['thread_root_id' => $rootId]);
            }

            $root = DB::table('comments')->where('id', $rootId)->first(['id', 'post_id']);
            if ($root) {
                $this->propagate([$rootId => ['root_id' => $rootId, 'post_id' => (int) $root->post_id]], $chunkSize);
            }
        }

        $this->info('Comment thread roots backfill completed.');

        return self::SUCCESS;
    }

    /** @param array<int, int> $rootById */
    private function setThreadRoots(array $ids, array $rootById): void
    {
        if ($ids === []) {
            return;
        }

        $grammar = DB::connection()->getQueryGrammar();
        $table = $grammar->wrapTable('comments');
        $idColumn = $grammar->wrap('id');
        $rootColumn = $grammar->wrap('thread_root_id');
        $cases = [];
        $bindings = [];

        foreach ($rootById as $id => $rootId) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = (int) $id;
            $bindings[] = (int) $rootId;
        }

        $idPlaceholders = implode(', ', array_fill(0, count($ids), '?'));
        $bindings = [...$bindings, ...array_map('intval', $ids)];
        DB::update(
            "UPDATE {$table} SET {$rootColumn} = CASE {$idColumn} ".implode(' ', $cases)." ELSE {$rootColumn} END WHERE {$rootColumn} IS NULL AND {$idColumn} IN ({$idPlaceholders})",
            $bindings,
        );
    }

    /**
     * @param array<int, array{root_id: int, post_id: int}> $frontier
     */
    private function propagate(array $frontier, int $chunkSize): void
    {
        while ($frontier !== []) {
            $next = [];

            foreach (array_chunk($frontier, $chunkSize, true) as $parents) {
                $parentIds = array_map('intval', array_keys($parents));
                DB::table('comments')->whereIn('parent_id', $parentIds)->select('id', 'parent_id', 'post_id')->orderBy('id')->chunkById($chunkSize, function ($children) use ($parents, &$next) {
                    $assignments = [];

                    foreach ($children as $child) {
                        $parent = $parents[(int) $child->parent_id] ?? null;
                        if (! $parent || (int) $child->post_id !== $parent['post_id']) {
                            continue;
                        }

                        $id = (int) $child->id;
                        $assignments[$id] = $parent['root_id'];
                        $next[$id] = $parent;
                    }

                    $this->setThreadRoots(array_keys($assignments), $assignments);
                }, 'id');
            }

            $frontier = $next;
        }
    }
}
