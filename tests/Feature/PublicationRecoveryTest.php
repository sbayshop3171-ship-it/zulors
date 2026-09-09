<?php

namespace Tests\Feature;

use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserStatus;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Jobs\Media\CleanupPublication;
use App\Jobs\Media\FinalizePublication;
use App\Jobs\Media\ProcessPublicationItem;
use App\Models\Media;
use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Models\Post;
use App\Models\User;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Media\Publication\PublicationFinalizer;
use App\Services\Media\Publication\PublicationMediaProcessor;
use App\Services\Media\Publication\PublicationService;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PublicationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $processor;
    private MockInterface $r2;
    private ?User $owner = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        config([
            'media.publications.enabled' => false,
            'media.publications.timeout' => 600,
            'media.queue_connection' => 'sync',
            'media.cloudflare.r2.temp_disk' => 'r2_temp',
            'media.cloudflare.r2.final_disk' => 'r2_final',
            'notifications.push.enabled' => false,
            'notifications.email.enabled' => false,
        ]);
        foreach (['r2_temp', 'r2_final'] as $disk) {
            Storage::set($disk, Storage::fake('publication-recovery-'.getmypid().'-'.$disk));
        }
        Bus::fake();
        $this->processor = $this->mock(PublicationMediaProcessor::class);
        $this->r2 = $this->mock(R2DirectUploadService::class);
    }

    public function test_processing_persists_output_then_queues_finalization_and_cleanup_without_publishing(): void
    {
        $publication = $this->publication();
        $item = $publication->items[0];
        $queuedAt = $item->queued_at;
        $output = $this->writeOutput($item);
        $this->processor->shouldReceive('process')->once()->with(Mockery::on(function ($candidate) use ($item) {
            $this->assertSame($item->id, $candidate->id);
            $this->assertSame(1, $candidate->generation);
            $this->assertSame('processing', $candidate->status);
            $this->assertSame('processing', $candidate->publication->status);

            return true;
        }))->andReturn($output);

        $this->process($item);

        $this->assertSame('processed', $item->refresh()->status);
        $this->assertSame($output, $item->output);
        $this->assertSame(100, $item->progress);
        $this->assertTrue($queuedAt->equalTo($item->queued_at));
        $this->assertNull($item->original_deleted_at);
        $this->assertSame('processing', $publication->refresh()->status);
        Bus::assertDispatchedTimes(FinalizePublication::class, 1);
        Bus::assertDispatched(FinalizePublication::class, fn ($job) => $job->publicationId === $publication->id && $job->afterCommit === true);
        Bus::assertDispatchedTimes(CleanupPublication::class, 1);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id && $job->afterCommit === true);
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
        $this->assertNoPublicEntities();
    }

    public function test_published_video_worker_replaces_raw_media_without_republishing(): void
    {
        Event::fake([MediaProcessedEvent::class]);
        $publication = $this->publishedVideoPublication();
        $item = $publication->items[0];
        $media = Media::firstOrFail();
        $output = $this->writeOutput($item);
        $this->processor->shouldReceive('process')->once()->with(Mockery::on(function ($candidate) use ($item) {
            $this->assertSame($item->id, $candidate->id);
            $this->assertSame('processing', $candidate->status);
            $this->assertSame('published', $candidate->publication->status);

            return true;
        }))->andReturn($output);

        $this->process($item);

        $media->refresh();
        $this->assertSame('published', $publication->refresh()->status);
        $this->assertSame('processed', $item->refresh()->status);
        $this->assertSame($output, $item->output);
        $this->assertSame('r2_final', $media->disk);
        $this->assertSame($output['source_path'], $media->source_path);
        $this->assertSame('r2', data_get($media->metadata, 'provider'));
        $this->assertSame($publication->id, data_get($media->metadata, 'publication_id'));
        $this->assertSame($item->id, data_get($media->metadata, 'publication_item_id'));
        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseCount('media_publication_events', 0);
        Bus::assertNotDispatched(FinalizePublication::class);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id && $job->afterCommit === true);
        Event::assertDispatched(MediaProcessedEvent::class);
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
        Storage::disk('r2_final')->assertExists($output['source_path']);
    }

    public function test_cancellation_during_processing_discards_late_outputs_and_cannot_finalize(): void
    {
        $publication = $this->publication();
        $item = $publication->items[0];
        $output = $this->writeOutput($item);
        $other = $this->publication('processing', ['processed']);
        $otherPath = $other->items[0]->output['source_path'];
        $this->processor->shouldReceive('process')->once()->andReturnUsing(function () use ($publication, $output) {
            app(PublicationService::class)->cancel($publication);

            return $output;
        });

        $this->process($item);

        $this->assertSame('cancelled', $publication->refresh()->status);
        $this->assertNull($item->refresh()->output);
        Storage::disk('r2_final')->assertMissing($output['source_path']);
        Storage::disk('r2_final')->assertExists($otherPath);
        Bus::assertNotDispatched(FinalizePublication::class);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id);
        $this->assertNull($item->outputs_deleted_at);
        $this->finalize($publication);
        $this->assertNoPublicEntities();
    }

    public function test_generation_change_during_processing_discards_only_the_obsolete_outputs(): void
    {
        $publication = $this->publication();
        $item = $publication->items[0];
        $obsolete = $this->writeOutput($item);
        $current = $this->writeOutput($item, 2);
        $this->processor->shouldReceive('process')->once()->andReturnUsing(function () use ($item, $current, $obsolete) {
            $item->update(['generation' => 2, 'status' => 'processed', 'output' => $current, 'progress' => 100]);

            return $obsolete;
        });

        $this->process($item, 1);

        $this->assertSame(2, $item->refresh()->generation);
        $this->assertSame($current, $item->output);
        Storage::disk('r2_final')->assertMissing($obsolete['source_path']);
        Storage::disk('r2_final')->assertExists($current['source_path']);
        Bus::assertNotDispatched(FinalizePublication::class);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id);
        $this->assertNoPublicEntities();
    }

    public function test_late_output_delete_failure_is_retryable_instead_of_silently_succeeding(): void
    {
        $publication = $this->publication();
        $item = $publication->items[0];
        $output = $this->writeOutput($item);
        $finalDisk = Storage::disk('r2_final');
        $remote = Mockery::mock(FilesystemAdapter::class);
        $remote->shouldReceive('deleteDirectory')->once()->with($this->prefix($item))->andReturnFalse();
        Storage::set('r2_final', $remote);
        $this->processor->shouldReceive('process')->once()->andReturnUsing(function () use ($publication, $output) {
            $publication->update(['status' => 'cancelled']);

            return $output;
        });

        $this->assertRetryableFailure(fn () => $this->process($item));

        $this->assertNull($item->refresh()->output);
        $this->assertNull($item->outputs_deleted_at);
        $finalDisk->assertExists($output['source_path']);
        Bus::assertNotDispatched(FinalizePublication::class);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id);
        $this->assertNoPublicEntities();
    }

    public static function lateOutputDeletion(): array
    {
        return ['deleted immediately' => [true], 'remote deletion failed' => [false]];
    }

    #[DataProvider('lateOutputDeletion')]
    public function test_late_cancelled_result_reopens_output_cleanup_after_cleanup_already_ran(bool $deleteSucceeds): void
    {
        $publication = $this->publication();
        $item = $publication->items[0];
        $output = null;
        $finalDisk = Storage::disk('r2_final');
        $this->processor->shouldReceive('process')->once()->andReturnUsing(function () use ($publication, $item, &$output, $finalDisk, $deleteSucceeds) {
            app(PublicationService::class)->cancel($publication);
            $this->cleanup($publication);
            $this->assertNotNull($item->refresh()->original_deleted_at);
            $this->assertNotNull($item->outputs_deleted_at);
            $this->travel(1)->seconds();
            $output = $this->writeOutput($item);
            $remote = Mockery::mock(FilesystemAdapter::class);
            $remote->shouldReceive('deleteDirectory')->once()->with($this->prefix($item))
                ->andReturnUsing(fn ($path) => $deleteSucceeds ? $finalDisk->deleteDirectory($path) : false);
            Storage::set('r2_final', $remote);

            return $output;
        });

        if ($deleteSucceeds) {
            $this->process($item);
        } else {
            $this->assertRetryableFailure(fn () => $this->process($item));
        }
        Storage::set('r2_final', $finalDisk);

        $this->assertSame('cancelled', $publication->refresh()->status);
        $this->assertNull($item->refresh()->outputs_deleted_at);
        $this->assertNull($item->output);
        if ($deleteSucceeds) {
            $finalDisk->assertMissing($output['source_path']);
        } else {
            $finalDisk->assertExists($output['source_path']);
        }
        Bus::assertNotDispatched(FinalizePublication::class);
        Bus::fake();
        $this->reconcile();
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id);
        $this->cleanup($publication);
        $this->assertNotNull($item->refresh()->outputs_deleted_at);
        $finalDisk->assertMissing($output['source_path']);
    }

    public function test_processing_dispatch_is_unique_per_item_and_generation(): void
    {
        Bus::fake()->except([ProcessPublicationItem::class]);
        Queue::fake();
        $publication = $this->publication('processing', ['uploaded', 'uploaded']);
        $first = $publication->items[0];
        $second = $publication->items[1];

        ProcessPublicationItem::dispatch($first->id, 1);
        ProcessPublicationItem::dispatch($first->id, 1);
        ProcessPublicationItem::dispatch($first->id, 2);
        ProcessPublicationItem::dispatch($second->id, 1);

        Queue::assertPushed(ProcessPublicationItem::class, 3);
        $this->assertCount(1, Queue::pushed(ProcessPublicationItem::class, fn ($job) => $job->itemId === $first->id && $job->generation === 1));
        Queue::assertPushed(ProcessPublicationItem::class, fn ($job) => $job->itemId === $first->id && $job->generation === 2);
        Queue::assertPushed(ProcessPublicationItem::class, fn ($job) => $job->itemId === $second->id && $job->generation === 1);
    }

    public function test_lost_processing_dispatch_can_be_requeued_after_its_unique_lock_expires(): void
    {
        Bus::fake()->except([ProcessPublicationItem::class]);
        Queue::fake();
        $publication = $this->publication();
        $item = $publication->items[0];
        ProcessPublicationItem::dispatch($item->id, 1);

        $this->travel(899)->seconds();
        ProcessPublicationItem::dispatch($item->id, 1);
        Queue::assertPushed(ProcessPublicationItem::class, 1);
        $this->travel(2)->seconds();
        ProcessPublicationItem::dispatch($item->id, 1);
        Queue::assertPushed(ProcessPublicationItem::class, 2);
    }

    public static function ignoredWorkerStates(): array
    {
        return [
            'wrong generation' => ['processing', 'uploaded', 2],
            'cancelled publication' => ['cancelled', 'uploaded', 1],
            'published publication' => ['published', 'uploaded', 1],
            'pending upload' => ['uploading', 'pending', 1],
            'upload in progress' => ['uploading', 'uploading', 1],
            'failed item' => ['failed', 'failed', 1],
        ];
    }

    #[DataProvider('ignoredWorkerStates')]
    public function test_obsolete_or_ineligible_processing_jobs_do_nothing(string $publicationStatus, string $itemStatus, int $jobGeneration): void
    {
        $publication = $this->publication($publicationStatus, [$itemStatus]);
        $item = $publication->items[0];
        $beforePublication = $publication->getAttributes();
        $beforeItem = $item->getAttributes();

        $this->process($item, $jobGeneration);

        $this->assertSame($beforePublication, $publication->refresh()->getAttributes());
        $this->assertSame($beforeItem, $item->refresh()->getAttributes());
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
        Bus::assertNothingDispatched();
        $this->assertNoPublicEntities();
    }

    public function test_missing_records_are_safe_to_process_finalize_or_clean_up(): void
    {
        $missing = (string) Str::uuid();
        (new ProcessPublicationItem($missing, 1))->handle(app(PublicationService::class), $this->processor);
        (new ProcessPublicationItem($missing, 1))->failed(new RuntimeException('Worker failed.'));
        (new FinalizePublication($missing))->handle(app(PublicationFinalizer::class));
        (new CleanupPublication($missing))->handle($this->r2);
        Bus::assertNothingDispatched();
        $this->assertNoPublicEntities();
    }

    public function test_processed_item_replay_skips_encoding_and_recovers_finalization_dispatch(): void
    {
        $publication = $this->publication('processing', ['processed']);
        $item = $publication->items[0];
        $before = $item->getAttributes();
        $this->process($item);
        $this->assertSame($before, $item->refresh()->getAttributes());
        Bus::assertDispatchedTimes(FinalizePublication::class, 1);
        Bus::assertDispatched(FinalizePublication::class, fn ($job) => $job->publicationId === $publication->id);
        Bus::assertNotDispatched(CleanupPublication::class);
        Storage::disk('r2_final')->assertExists($item->output['source_path']);
    }

    public function test_processor_exception_propagates_and_exhausted_job_records_a_safe_failure(): void
    {
        $publication = $this->publication();
        $item = $publication->items[0];
        $error = new RuntimeException('Failed reading /private/source.mp4?signature=secret');
        $this->processor->shouldReceive('process')->once()->andThrow($error);
        $job = new ProcessPublicationItem($item->id, 1);

        $caught = $this->assertRetryableFailure(fn () => $job->handle(app(PublicationService::class), $this->processor));
        $this->assertSame($error, $caught);
        $this->assertSame('processing', $item->refresh()->status);
        $this->assertNull($item->output);
        $job->failed($caught);

        $this->assertSame('failed', $item->refresh()->status);
        $this->assertSame('failed', $publication->refresh()->status);
        $this->assertNotEmpty($publication->error);
        $this->assertStringNotContainsString('signature=secret', $publication->error);
        $this->assertStringNotContainsString('/private/', $publication->error);
        $this->assertNull($item->original_deleted_at);
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
        Bus::assertNothingDispatched();
        $this->assertNoPublicEntities();
    }

    public static function ignoredFailureStates(): array
    {
        return [
            'new generation' => ['processing', 'uploaded', 2],
            'processed successfully' => ['processing', 'processed', 1],
            'cancelled' => ['cancelled', 'processing', 1],
            'published' => ['published', 'processed', 1],
        ];
    }

    #[DataProvider('ignoredFailureStates')]
    public function test_late_worker_failure_cannot_overwrite_newer_or_terminal_results(string $publicationStatus, string $itemStatus, int $jobGeneration): void
    {
        $publication = $this->publication($publicationStatus, [$itemStatus]);
        $item = $publication->items[0];
        $beforePublication = $publication->getAttributes();
        $beforeItem = $item->getAttributes();
        (new ProcessPublicationItem($item->id, $jobGeneration))->failed(new RuntimeException('Old worker failed.'));
        $this->assertSame($beforePublication, $publication->refresh()->getAttributes());
        $this->assertSame($beforeItem, $item->refresh()->getAttributes());
        Bus::assertNothingDispatched();
    }

    public function test_finalization_waits_for_all_worker_outputs_and_replays_publish_once(): void
    {
        $publication = $this->publication('processing', ['uploaded', 'uploaded']);
        foreach ($publication->items as $item) {
            $output = $this->writeOutput($item);
            $this->processor->shouldReceive('process')->once()
                ->with(Mockery::on(fn ($candidate) => $candidate->id === $item->id))->andReturn($output);
        }

        $this->process($publication->items[0]);
        $this->finalize($publication);
        $this->assertNoPublicEntities();
        $this->assertSame('processing', $publication->refresh()->status);

        $this->process($publication->items[1]);
        $this->finalize($publication);
        $result = $publication->refresh()->result;
        $publishedAt = $publication->published_at;
        $this->assertSame('published', $publication->status);
        $this->assertSame('post', $result['type']);
        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('media', 2);
        $this->assertDatabaseCount('media_publication_events', 3);

        $this->travel(1)->minutes();
        $this->finalize($publication);
        $this->assertSame($result, $publication->refresh()->result);
        $this->assertTrue($publishedAt->equalTo($publication->published_at));
        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('media', 2);
        $this->assertDatabaseCount('media_publication_events', 3);
        $this->assertSame(1, (int) $this->owner->refresh()->publications_count);
    }

    public function test_finalization_failure_preserves_processed_outputs_for_retry(): void
    {
        $publication = $this->publication('publishing', ['processed']);
        $item = $publication->items[0];
        $before = $item->getAttributes();
        (new FinalizePublication($publication->id))->failed(new RuntimeException('Internal database credentials: secret'));
        $this->assertSame('failed', $publication->refresh()->status);
        $this->assertNotEmpty($publication->error);
        $this->assertStringNotContainsString('secret', $publication->error);
        $this->assertSame($before, $item->refresh()->getAttributes());
        Storage::disk('r2_final')->assertExists($item->output['source_path']);
        $this->assertNoPublicEntities();
        Bus::assertNothingDispatched();
    }

    public static function terminalStatuses(): array
    {
        return ['published' => ['published'], 'cancelled' => ['cancelled']];
    }

    #[DataProvider('terminalStatuses')]
    public function test_late_finalization_failure_preserves_terminal_publications(string $status): void
    {
        $publication = $this->publication($status, ['processed']);
        $before = $publication->getAttributes();
        (new FinalizePublication($publication->id))->failed(new RuntimeException('Late failure.'));
        $this->assertSame($before, $publication->refresh()->getAttributes());
        Bus::assertNothingDispatched();
    }

    public function test_cleanup_deletes_processed_raw_once_and_keeps_optimized_media(): void
    {
        $publication = $this->publication('processing', ['processed']);
        $item = $publication->items[0];
        $rawDisk = Storage::disk('r2_temp');
        $remote = Mockery::mock(FilesystemAdapter::class);
        $remote->shouldReceive('delete')->once()->with($item->upload['path'])
            ->andReturnUsing(fn ($path) => $rawDisk->delete($path));
        Storage::set('r2_temp', $remote);
        $this->cleanup($publication);
        $deletedAt = $item->refresh()->original_deleted_at;
        $this->assertNotNull($deletedAt);
        $rawDisk->assertMissing($item->upload['path']);
        Storage::disk('r2_final')->assertExists($item->output['source_path']);
        $this->assertNull($item->outputs_deleted_at);

        $this->travel(1)->minutes();
        $this->cleanup($publication);
        $this->assertTrue($deletedAt->equalTo($item->refresh()->original_deleted_at));
        $this->assertSame('processing', $publication->refresh()->status);
        $this->assertSame('processed', $item->status);
    }

    public function test_false_raw_delete_is_retryable_and_cannot_mark_the_original_deleted(): void
    {
        $publication = $this->publication('processing', ['processed']);
        $item = $publication->items[0];
        $rawDisk = Storage::disk('r2_temp');
        $remote = Mockery::mock(FilesystemAdapter::class);
        $remote->shouldReceive('delete')->once()->with($item->upload['path'])->andReturnFalse();
        $remote->shouldReceive('delete')->once()->with($item->upload['path'])
            ->andReturnUsing(fn ($path) => $rawDisk->delete($path));
        Storage::set('r2_temp', $remote);

        $this->assertRetryableFailure(fn () => $this->cleanup($publication));
        $this->assertNull($item->refresh()->original_deleted_at);
        $rawDisk->assertExists($item->upload['path']);
        Storage::disk('r2_final')->assertExists($item->output['source_path']);

        $this->cleanup($publication);
        $this->assertNotNull($item->refresh()->original_deleted_at);
        $rawDisk->assertMissing($item->upload['path']);
        Storage::disk('r2_final')->assertExists($item->output['source_path']);
    }

    public function test_cleanup_preserves_raw_sources_until_processing_succeeds_or_publication_is_cancelled(): void
    {
        $publication = $this->publication('processing', ['pending', 'uploaded', 'processing', 'failed']);
        $this->cleanup($publication);
        foreach ($publication->items as $item) {
            $this->assertNull($item->refresh()->original_deleted_at);
            Storage::disk('r2_temp')->assertExists($item->upload['path']);
        }
        Bus::assertNothingDispatched();
    }

    public function test_cancelled_cleanup_removes_all_generations_without_deleting_other_publications(): void
    {
        $publication = $this->publication('cancelled', ['processing']);
        $item = $publication->items[0];
        $first = $this->writeOutput($item);
        $second = $this->writeOutput($item, 2);
        $other = $this->publication('processing', ['processed']);

        $this->cleanup($publication);

        Storage::disk('r2_temp')->assertMissing($item->upload['path']);
        Storage::disk('r2_final')->assertMissing($first['source_path']);
        Storage::disk('r2_final')->assertMissing($second['source_path']);
        Storage::disk('r2_temp')->assertExists($other->items[0]->upload['path']);
        Storage::disk('r2_final')->assertExists($other->items[0]->output['source_path']);
        $this->assertNotNull($item->refresh()->original_deleted_at);
        $this->assertNotNull($item->outputs_deleted_at);
        $this->assertSame('cancelled', $publication->refresh()->status);
    }

    public function test_cancelled_output_delete_failure_can_retry_after_the_raw_source_is_already_deleted(): void
    {
        $publication = $this->publication('cancelled', ['processed']);
        $item = $publication->items[0];
        $finalDisk = Storage::disk('r2_final');
        $remote = Mockery::mock(FilesystemAdapter::class);
        $remote->shouldReceive('deleteDirectory')->once()->with($this->prefix($item, false))->andReturnFalse();
        $remote->shouldReceive('deleteDirectory')->once()->with($this->prefix($item, false))
            ->andReturnUsing(fn ($path) => $finalDisk->deleteDirectory($path));
        Storage::set('r2_final', $remote);

        $this->assertRetryableFailure(fn () => $this->cleanup($publication));
        $deletedAt = $item->refresh()->original_deleted_at;
        $this->assertNotNull($deletedAt);
        $this->assertNull($item->outputs_deleted_at);
        Storage::disk('r2_temp')->assertMissing($item->upload['path']);
        $finalDisk->assertExists($item->output['source_path']);

        $this->travel(1)->minutes();
        $this->cleanup($publication);
        $this->assertTrue($deletedAt->equalTo($item->refresh()->original_deleted_at));
        $this->assertNotNull($item->outputs_deleted_at);
        $finalDisk->assertMissing($item->output['source_path']);
    }

    public function test_missing_multipart_session_does_not_prevent_idempotent_raw_cleanup(): void
    {
        $publication = $this->publication('cancelled');
        $item = $publication->items[0];
        $upload = array_replace($item->upload, ['upload_type' => 'multipart', 'upload_id' => 'expired-upload']);
        $item->update(['upload' => $upload]);
        $this->r2->shouldReceive('abortMultipartUpload')->once()->with($upload['path'], 'expired-upload', 'r2_temp')
            ->andThrow(new S3Exception('Expired multipart upload.', new Command('AbortMultipartUpload'), ['code' => 'NoSuchUpload']));

        $this->cleanup($publication);
        $this->cleanup($publication);

        $this->assertNotNull($item->refresh()->original_deleted_at);
        Storage::disk('r2_temp')->assertMissing($upload['path']);
    }

    public function test_multipart_access_denied_preserves_raw_bytes_and_the_cleanup_marker(): void
    {
        $publication = $this->publication('cancelled');
        $item = $publication->items[0];
        $upload = array_replace($item->upload, ['upload_type' => 'multipart', 'upload_id' => 'private-upload']);
        $item->update(['upload' => $upload]);
        $error = new S3Exception('Access denied.', new Command('AbortMultipartUpload'), ['code' => 'AccessDenied']);
        $this->r2->shouldReceive('abortMultipartUpload')->once()->with($upload['path'], 'private-upload', 'r2_temp')->andThrow($error);

        try {
            $this->cleanup($publication);
            $this->fail('AccessDenied must leave cleanup eligible for retry.');
        } catch (S3Exception $caught) {
            $this->assertSame($error, $caught);
        }
        $this->assertNull($item->refresh()->original_deleted_at);
        Storage::disk('r2_temp')->assertExists($upload['path']);
    }

    public static function expiredStatuses(): array
    {
        return ['uploading' => ['uploading'], 'processing' => ['processing'], 'failed' => ['failed']];
    }

    #[DataProvider('expiredStatuses')]
    public function test_reconcile_cancels_expired_publications_and_recovers_cleanup_with_rollout_disabled(string $status): void
    {
        $publication = $this->publication($status);
        $publication->update(['expires_at' => now()->subSecond()]);
        $this->reconcile();
        $this->assertSame('cancelled', $publication->refresh()->status);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id);
        Bus::assertNotDispatched(ProcessPublicationItem::class);
        Bus::assertNotDispatched(FinalizePublication::class);
        $this->cleanup($publication);
        Storage::disk('r2_temp')->assertMissing($publication->items[0]->upload['path']);
        $this->assertNotNull($publication->items[0]->refresh()->original_deleted_at);
        $this->assertNoPublicEntities();
    }

    public static function lostDispatches(): array
    {
        return [
            'uploaded without dispatch' => ['uploaded', null],
            'uploaded stale dispatch' => ['uploaded', 301],
            'processing without dispatch' => ['processing', null],
            'processing beyond timeout and grace' => ['processing', 721],
        ];
    }

    #[DataProvider('lostDispatches')]
    public function test_reconcile_recovers_lost_worker_dispatch_without_resetting_queue_age(string $status, ?int $age): void
    {
        $publication = $this->publication('processing', [$status]);
        $item = $publication->items[0];
        $item->update(['dispatched_at' => $age === null ? null : now()->subSeconds($age), 'generation' => 3]);
        $queuedAt = $item->queued_at;
        $this->reconcile();
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
        Bus::assertDispatched(ProcessPublicationItem::class, fn ($job) => $job->itemId === $item->id && $job->generation === 3 && $job->afterCommit === true);
        $this->assertTrue(now()->equalTo($item->refresh()->dispatched_at));
        $this->assertTrue($queuedAt->equalTo($item->queued_at));
        $this->assertSame($status, $item->status);
        $this->reconcile();
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
        $this->assertNoPublicEntities();
    }

    public static function liveDispatches(): array
    {
        return [
            'fresh upload dispatch' => ['uploaded', 299],
            'upload threshold' => ['uploaded', 300],
            'active worker' => ['processing', 600],
            'processing grace threshold' => ['processing', 720],
            'unuploaded item' => ['pending', 1000],
        ];
    }

    #[DataProvider('liveDispatches')]
    public function test_reconcile_does_not_duplicate_live_processing_or_dispatch_pending_uploads(string $status, int $age): void
    {
        $publication = $this->publication('processing', [$status]);
        $item = $publication->items[0];
        $item->update(['dispatched_at' => now()->subSeconds($age)]);
        $before = $item->getAttributes();
        $this->reconcile();
        Bus::assertNotDispatched(ProcessPublicationItem::class);
        $this->assertSame($before, $item->refresh()->getAttributes());
    }

    public function test_reconcile_recovers_lost_finalization_only_after_all_items_are_processed(): void
    {
        $ready = $this->publication('processing', ['processed', 'processed']);
        $partial = $this->publication('processing', ['processed', 'uploaded']);
        $this->reconcile();
        Bus::assertDispatchedTimes(FinalizePublication::class, 1);
        Bus::assertDispatched(FinalizePublication::class, fn ($job) => $job->publicationId === $ready->id && $job->afterCommit === true);
        Bus::assertNotDispatched(FinalizePublication::class, fn ($job) => $job->publicationId === $partial->id);
        $this->assertNoPublicEntities();
    }

    public function test_reconcile_preserves_failed_work_until_expiry_or_explicit_retry(): void
    {
        $publication = $this->publication('failed', ['uploaded']);
        $item = $publication->items[0];
        $item->update(['dispatched_at' => null]);
        $this->reconcile();
        $this->assertSame('failed', $publication->refresh()->status);
        $this->assertNull($item->refresh()->dispatched_at);
        Bus::assertNothingDispatched();
        Storage::disk('r2_temp')->assertExists($item->upload['path']);
    }

    public static function scanBlockers(): array
    {
        return ['failed draft' => ['failed', 'failed'], 'waiting upload' => ['uploading', 'pending'], 'healthy worker' => ['processing', 'processing']];
    }

    #[DataProvider('scanBlockers')]
    public function test_reconcile_scan_is_fair_when_older_publications_need_no_action(string $status, string $itemStatus): void
    {
        $blocker = $this->publication($status, [$itemStatus]);
        $blocker->forceFill(['updated_at' => now()->subMinutes(20)])->save();
        $recoverable = $this->publication();
        $recoverable->forceFill(['updated_at' => now()->subMinutes(10)])->save();
        $recoverable->items[0]->update(['dispatched_at' => null]);

        for ($pass = 0; $pass < 3; $pass++) {
            $this->reconcile(1);
            $this->travel(1)->seconds();
        }

        Bus::assertDispatched(ProcessPublicationItem::class, fn ($job) => $job->itemId === $recoverable->items[0]->id);
        $this->assertNotNull($recoverable->items[0]->refresh()->dispatched_at);
    }

    public function test_reconcile_eventually_expires_a_draft_behind_an_older_failed_record(): void
    {
        $blocker = $this->publication('failed', ['failed']);
        $blocker->forceFill(['updated_at' => now()->subMinutes(20)])->save();
        $expired = $this->publication('uploading', ['pending']);
        $expired->forceFill(['updated_at' => now()->subMinutes(10), 'expires_at' => now()->subSecond()])->save();
        for ($pass = 0; $pass < 3; $pass++) {
            $this->reconcile(1);
            $this->travel(1)->seconds();
        }
        $this->assertSame('cancelled', $expired->refresh()->status);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $expired->id);
    }

    public function test_reconcile_recovers_terminal_raw_cleanup_without_reprocessing(): void
    {
        $published = $this->publication('published', ['processed']);
        $cancelled = $this->publication('cancelled', ['uploaded']);
        $cleaned = $this->publication('published', ['processed']);
        $this->cleanup($cleaned);
        $this->reconcile();
        Bus::assertDispatchedTimes(CleanupPublication::class, 2);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $published->id);
        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $cancelled->id);
        Bus::assertNotDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $cleaned->id);
        Bus::assertNotDispatched(ProcessPublicationItem::class);
        Bus::assertNotDispatched(FinalizePublication::class);
    }

    public function test_reconcile_recovers_cancelled_output_cleanup_after_raw_deletion_succeeded(): void
    {
        $publication = $this->publication('cancelled', ['processed']);
        $item = $publication->items[0];
        $finalDisk = Storage::disk('r2_final');
        $remote = Mockery::mock(FilesystemAdapter::class);
        $remote->shouldReceive('deleteDirectory')->once()->with($this->prefix($item, false))->andReturnFalse();
        Storage::set('r2_final', $remote);
        $this->assertRetryableFailure(fn () => $this->cleanup($publication));
        $this->assertNotNull($item->refresh()->original_deleted_at);
        $finalDisk->assertExists($item->output['source_path']);
        Storage::set('r2_final', $finalDisk);

        $this->reconcile();

        Bus::assertDispatched(CleanupPublication::class, fn ($job) => $job->publicationId === $publication->id);
        $this->cleanup($publication);
        $finalDisk->assertMissing($item->output['source_path']);
        $this->assertNotNull($item->refresh()->outputs_deleted_at);
    }

    private function process(MediaPublicationItem $item, ?int $generation = null): void
    {
        (new ProcessPublicationItem($item->id, $generation ?? $item->generation))
            ->handle(app(PublicationService::class), $this->processor);
    }

    private function finalize(MediaPublication $publication): void
    {
        (new FinalizePublication($publication->id))->handle(app(PublicationFinalizer::class));
    }

    private function cleanup(MediaPublication $publication): void
    {
        (new CleanupPublication($publication->id))->handle($this->r2);
    }

    private function reconcile(int $limit = 50): void
    {
        $this->artisan('media:reconcile-publications', ['--limit' => $limit])->assertSuccessful();
    }

    private function assertRetryableFailure(callable $operation): RuntimeException
    {
        try {
            $operation();
        } catch (RuntimeException $exception) {
            return $exception;
        }

        $this->fail('The operation must throw so that the queue can retry incomplete work.');
    }

    private function prefix(MediaPublicationItem $item, int|bool|null $generation = null): string
    {
        $prefix = 'uploads/publications/'.$item->publication_id.'/'.$item->id;

        return $generation === false ? $prefix : $prefix.'/'.($generation ?? $item->generation);
    }

    private function writeOutput(MediaPublicationItem $item, ?int $generation = null): array
    {
        $video = $item->type === 'video';
        $body = $video ? 'encoded-video' : 'encoded-image';
        $path = $this->prefix($item, $generation).($video ? '/optimized.mp4' : '/image.webp');
        Storage::disk('r2_final')->put($path, $body);

        return [
            'disk' => 'r2_final', 'source_path' => $path, 'mime' => $video ? 'video/mp4' : 'image/webp',
            'extension' => $video ? 'mp4' : 'webp', 'size' => strlen($body),
            'metadata' => array_filter([
                'provider' => $video ? 'r2' : null,
                'duration_seconds' => $video ? 10 : null,
                'dimensions' => ['width' => 16, 'height' => 16],
                'processing_state' => $video ? 'processed' : null,
                'processing_progress' => $video ? 100 : null,
            ], fn ($value) => $value !== null),
        ];
    }

    private function publishedVideoPublication(): MediaPublication
    {
        $this->owner ??= $this->createUser();
        $publication = MediaPublication::create([
            'id' => (string) Str::uuid(), 'client_uid' => (string) Str::uuid(), 'user_id' => $this->owner->id,
            'request_hash' => str_repeat('v', 64), 'kind' => 'post', 'status' => 'published', 'has_video' => true,
            'payload' => ['kind' => 'post', 'content' => 'raw video', 'privacy' => 'all', 'selected_user_ids' => []],
            'profile' => [], 'expires_at' => now()->addDays(3), 'published_at' => now(),
        ]);
        $id = (string) Str::uuid();
        $path = 'tmp/publications/'.$publication->id.'/'.$id.'/raw.mp4';
        Storage::disk('r2_temp')->put($path, str_repeat('v', 24));
        $item = $publication->items()->create([
            'id' => $id, 'client_uid' => (string) Str::uuid(), 'position' => 0,
            'name' => 'raw.mp4', 'mime' => 'video/mp4', 'size' => 24, 'type' => 'video',
            'status' => 'uploaded', 'generation' => 1, 'progress' => 100,
            'metadata' => ['duration_seconds' => 10], 'dispatched_at' => now(), 'queued_at' => now(),
            'upload' => ['disk' => 'r2_temp', 'path' => $path, 'upload_type' => 'raw', 'final_disk' => 'r2_final'],
        ]);
        $post = Post::create([
            'user_id' => $this->owner->id, 'content' => 'raw video', 'type' => PostType::VIDEO,
            'status' => PostStatus::ACTIVE,
        ]);
        $post->media()->create([
            'source_path' => $path, 'disk' => 'r2_temp', 'type' => MediaType::VIDEO,
            'status' => MediaStatus::PROCESSED, 'extension' => 'mp4', 'mime' => 'video/mp4',
            'size' => 24, 'metadata' => [
                'publication_id' => $publication->id,
                'publication_item_id' => $item->id,
                'provider' => 'r2_direct',
                'temp_disk' => 'r2_temp',
                'temp_path' => $path,
                'upload_state' => 'uploaded',
                'processing_state' => 'queued',
                'processing_progress' => 100,
                'instant_publish' => true,
            ],
        ]);
        $publication->update(['result' => ['id' => $post->id, 'type' => 'post', 'url' => $post->url]]);

        return $publication->refresh()->load('items');
    }

    private function publication(string $status = 'processing', array $itemStatuses = ['uploaded']): MediaPublication
    {
        $this->owner ??= $this->createUser();
        $publication = MediaPublication::create([
            'id' => (string) Str::uuid(), 'client_uid' => (string) Str::uuid(), 'user_id' => $this->owner->id,
            'request_hash' => str_repeat('a', 64), 'kind' => 'post', 'status' => $status, 'has_video' => false,
            'payload' => ['kind' => 'post', 'content' => '', 'privacy' => 'all', 'selected_user_ids' => []],
            'profile' => [], 'expires_at' => now()->addDays(3),
        ]);
        foreach ($itemStatuses as $position => $itemStatus) {
            $id = (string) Str::uuid();
            $path = 'tmp/publications/'.$publication->id.'/'.$id.'/original.jpg';
            $item = $publication->items()->create([
                'id' => $id, 'client_uid' => (string) Str::uuid(), 'position' => $position,
                'name' => 'original.jpg', 'mime' => 'image/jpeg', 'size' => 16, 'type' => 'image',
                'status' => $itemStatus, 'generation' => 1, 'progress' => $itemStatus === 'processed' ? 100 : 0,
                'metadata' => [], 'dispatched_at' => now(), 'queued_at' => now()->subMinutes(20),
                'upload' => ['disk' => 'r2_temp', 'path' => $path, 'upload_type' => 'raw', 'final_disk' => 'r2_final'],
            ]);
            Storage::disk('r2_temp')->put($path, str_repeat('x', 16));
            if ($itemStatus === 'processed') {
                $item->update(['output' => $this->writeOutput($item)]);
            }
        }

        return $publication->refresh()->load('items');
    }

    private function assertNoPublicEntities(): void
    {
        foreach (['posts', 'stories', 'story_frames', 'messages', 'media', 'media_publication_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function createUser(): User
    {
        $username = 'recovery-'.Str::lower(Str::random(10));

        return User::create([
            'first_name' => 'Recovery', 'last_name' => 'Tester', 'username' => $username,
            'caption' => '@'.$username, 'email' => $username.'@example.test', 'password' => bcrypt('password'),
            'phone' => '', 'website' => '', 'bio' => '', 'gender' => 'male', 'language' => 'en',
            'last_active' => now()->timestamp, 'tips' => [], 'email_verified_at' => now(),
            'role' => 'user', 'theme' => 'light', 'status' => UserStatus::ACTIVE, 'type' => 'author',
            'publications_count' => 0, 'followers_count' => 0, 'following_count' => 0,
        ]);
    }
}
