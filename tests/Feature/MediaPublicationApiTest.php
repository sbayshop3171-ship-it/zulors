<?php

namespace Tests\Feature;

use App\Enums\Chat\ChatType;
use App\Enums\User\UserStatus;
use App\Jobs\Media\CleanupPublication;
use App\Jobs\Media\FinalizePublication;
use App\Jobs\Media\ProcessPublicationItem;
use App\Models\Chat;
use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Models\User;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Safety\SafetyService;
use Aws\Command;
use Aws\S3\Exception\S3Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaPublicationApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/media-publications';

    private MockInterface $r2;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'media.publications.enabled' => true,
            'media.publications.allowed_user_ids' => [],
            'media.publications.kinds' => ['post', 'story', 'chat'],
            'media.publications.per_user_limit' => 2,
            'media.publications.video_limit' => 2,
            'media.publications.max_queue_age_seconds' => 900,
            'media.uploads.video.max_bytes' => 2048,
            'media.uploads.video.max_duration_seconds' => 600,
            'post.max_images_count' => 3,
            'post.validation.image.max' => 1,
            'post.validation.video.max' => 2,
            'story.validation.image.max' => 1,
            'story.validation.video.max' => 2,
            'story.video_clip_size' => 60,
            'chat.validation.message.media.max' => 2,
            'media.queue_connection' => 'sync',
        ]);

        foreach (['r2_temp', 'r2_final'] as $disk) {
            Storage::set($disk, Storage::fake('media-publication-api-'.getmypid().'-'.$disk));
        }
        Bus::fake();
        // Admission quotas must be exercised independently of the generic API throttle.
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->r2 = $this->mock(R2DirectUploadService::class);
        $this->r2->shouldReceive('isConfigured')->andReturnTrue()->byDefault();
    }

    public function test_feature_flag_and_canary_control_capabilities_and_new_admission(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $this->actingAs($owner);
        config(['media.publications.enabled' => false]);

        $this->getJson(self::API.'/capabilities')->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.user_id', $owner->id)
            ->assertJsonPath('data.kinds', ['post', 'story', 'chat'])
            ->assertJsonPath('data.upload_concurrency', 2);
        $this->postJson(self::API, $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('media_publications', 0);

        config(['media.publications.enabled' => true, 'media.publications.allowed_user_ids' => [(string) $owner->id]]);
        $this->getJson(self::API.'/capabilities')->assertOk()->assertJsonPath('data.enabled', true);
        $this->postJson(self::API, $this->payload())->assertCreated();

        $this->actingAs($other);
        $this->getJson(self::API.'/capabilities')->assertOk()->assertJsonPath('data.enabled', false);
        $this->postJson(self::API, $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('media_publications', 1);
        Bus::assertNothingDispatched();
        $this->assertNoPublishedEntities();
    }

    public function test_unconfigured_r2_disables_admission_without_a_proxy_fallback(): void
    {
        $this->r2->shouldReceive('isConfigured')->andReturnFalse();
        $this->actingAs($this->createUser());
        $this->getJson(self::API.'/capabilities')->assertOk()->assertJsonPath('data.enabled', false);
        $this->postJson(self::API, $this->payload())->assertStatus(503);
        $this->assertDatabaseCount('media_publications', 0);
        $this->assertNoPublishedEntities();
    }

    public function test_kind_rollout_blocks_only_new_publications_of_disabled_kinds(): void
    {
        $this->actingAs($this->createUser());
        config(['media.publications.kinds' => ['post']]);
        $this->postJson(self::API, $this->payload('story'))->assertStatus(503);
        $this->postJson(self::API, $this->payload())->assertCreated();
        $this->assertDatabaseCount('media_publications', 1);
    }

    public static function kinds(): array
    {
        return ['post' => ['post'], 'story' => ['story'], 'chat' => ['chat']];
    }

    #[DataProvider('kinds')]
    public function test_admission_creates_only_outbox_records_and_keeps_distinct_pending_drafts(string $kind): void
    {
        $owner = $this->createUser();
        $this->actingAs($owner);
        $extra = $kind === 'chat' ? ['chat_id' => $this->createChat($owner)->chat_id] : [];
        $firstPayload = array_replace($this->payload($kind), $extra, ['content' => 'First selection']);
        $secondPayload = array_replace($this->payload($kind), $extra, ['content' => 'Second selection']);

        $first = $this->postJson(self::API, $firstPayload)->assertCreated()
            ->assertJsonPath('data.status', 'uploading')->assertJsonPath('data.result', null)
            ->assertJsonPath('data.items.0.status', 'pending')->assertJsonPath('data.items.0.progress', 0)->json('data');
        $second = $this->postJson(self::API, $secondPayload)->assertCreated()->json('data');

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertNotSame($first['items'][0]['id'], $second['items'][0]['id']);
        $this->assertDatabaseCount('media_publications', 2);
        $this->assertDatabaseCount('media_publication_items', 2);
        $this->assertEquals($firstPayload, MediaPublication::findOrFail($first['id'])->payload);
        $this->getJson(self::API)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::API.'/'.$first['id'])->assertOk()->assertJsonPath('data.content', 'First selection');
        $this->getJson(self::API.'/'.$second['id'])->assertOk()->assertJsonPath('data.content', 'Second selection');
        $this->assertNoPublishedEntities();
        Storage::disk('r2_temp')->assertDirectoryEmpty('/');
        Bus::assertNothingDispatched();
    }

    public function test_idempotency_is_per_user_and_accepts_canonical_key_order_and_header(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $this->actingAs($owner);
        $payload = $this->payload();
        $first = $this->postJson(self::API, $payload)->assertCreated()->json('data');
        $reordered = array_reverse($payload, true);
        $reordered['items'][0] = array_reverse($reordered['items'][0], true);
        $this->postJson(self::API, $reordered)->assertCreated()->assertJsonPath('data.id', $first['id'])
            ->assertJsonPath('data.items.0.id', $first['items'][0]['id']);
        $this->postJson(self::API, Arr::except($payload, 'client_uid'), ['Idempotency-Key' => $payload['client_uid']])
            ->assertCreated()->assertJsonPath('data.id', $first['id']);
        $this->assertDatabaseCount('media_publications', 1);
        $this->assertDatabaseCount('media_publication_items', 1);

        $this->actingAs($other);
        $second = $this->postJson(self::API, $payload)->assertCreated()->json('data');
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertDatabaseHas('media_publications', ['id' => $first['id'], 'user_id' => $owner->id]);
        $this->assertDatabaseHas('media_publications', ['id' => $second['id'], 'user_id' => $other->id]);
        $this->assertDatabaseCount('media_publication_items', 2);
    }

    public static function changedRequests(): array
    {
        return [
            'caption' => ['content', 'Different caption'],
            'kind' => ['kind', 'story'],
            'file bytes' => ['items.0.size', 17],
            'file name' => ['items.0.name', 'different.jpg'],
            'file identity' => ['items.0.client_uid', 'c01e2000-0000-4000-8000-000000000001'],
            'dimensions' => ['items.0.width', 640],
            'marks' => ['marks.is_sensitive', true],
        ];
    }

    #[DataProvider('changedRequests')]
    public function test_reusing_an_idempotency_key_for_changed_content_conflicts(string $key, mixed $value): void
    {
        $this->actingAs($this->createUser());
        $payload = $this->payload();
        $id = $this->postJson(self::API, $payload)->assertCreated()->json('data.id');
        $changed = $payload;
        Arr::set($changed, $key, $value);
        $this->postJson(self::API, $changed)->assertConflict();
        $this->assertSame($payload, MediaPublication::findOrFail($id)->payload);
        $this->assertDatabaseCount('media_publications', 1);
        $this->assertDatabaseCount('media_publication_items', 1);
    }

    public static function routes(): array
    {
        return [
            'capabilities' => ['GET', '/capabilities'], 'list' => ['GET', ''],
            'create' => ['POST', ''], 'show' => ['GET', '/{id}'],
            'retry' => ['POST', '/{id}/retry'], 'cancel' => ['DELETE', '/{id}'],
            'resume' => ['POST', '/{id}/items/{item}/resume'],
            'complete' => ['POST', '/{id}/items/{item}/complete'],
        ];
    }

    #[DataProvider('routes')]
    public function test_every_route_requires_authentication(string $method, string $route): void
    {
        $url = strtr(self::API.$route, ['{id}' => (string) Str::uuid(), '{item}' => (string) Str::uuid()]);
        $this->json($method, $url, $this->payload() + ['generation' => 1, 'parts' => []])->assertUnauthorized();
        $this->assertDatabaseCount('media_publications', 0);
    }

    public function test_ownership_is_enforced_on_every_publication_and_item_route(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $this->actingAs($owner);
        $publication = $this->createPublication();
        $before = $publication->getAttributes();

        $this->actingAs($other);
        $own = $this->createPublication();
        $this->getJson(self::API)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        foreach (['GET' => [''], 'DELETE' => [''], 'POST' => ['/retry', '/items/'.$publication->items[0]->id.'/resume', '/items/'.$publication->items[0]->id.'/complete']] as $method => $suffixes) {
            foreach ($suffixes as $suffix) {
                $this->json($method, self::API.'/'.$publication->id.$suffix, ['generation' => 1, 'parts' => []])->assertNotFound();
            }
        }
        foreach (['resume', 'complete'] as $action) {
            $this->postJson(self::API.'/'.$own->id.'/items/'.$publication->items[0]->id.'/'.$action, ['generation' => 1, 'parts' => []])->assertNotFound();
        }
        $this->assertSame($before, $publication->refresh()->getAttributes());
        Bus::assertNothingDispatched();
    }

    public static function invalidDescriptors(): array
    {
        return [
            'invalid publication uuid' => ['client_uid', 'invalid', 'client_uid'],
            'invalid kind' => ['kind', 'album', 'kind'],
            'empty items' => ['items', [], 'items'],
            'unknown type' => ['items.0.type', 'audio', 'items.0.type'],
            'image mime mismatch' => ['items.0.mime', 'video/mp4', 'items.0'],
            'executable mime' => ['items.0.mime', 'application/x-php', 'items.0'],
            'svg image' => ['items.0.mime', 'image/svg+xml', 'items.0'],
            'zero bytes' => ['items.0.size', 0, 'items.0.size'],
            'negative bytes' => ['items.0.size', -1, 'items.0.size'],
            'fractional bytes' => ['items.0.size', 1.5, 'items.0.size'],
            'oversized image' => ['items.0.size', 1025, 'items.0'],
            'invalid width' => ['items.0.width', 0, 'items.0.width'],
            'invalid height' => ['items.0.height', -1, 'items.0.height'],
            'empty filename' => ['items.0.name', '', 'items.0.name'],
            'oversized filename' => ['items.0.name', str_repeat('a', 256), 'items.0.name'],
            'invalid item uuid' => ['items.0.client_uid', 'invalid', 'items.0.client_uid'],
            'negative clip start' => ['clip_start_seconds', -1, 'clip_start_seconds'],
            'zero clip duration' => ['clip_duration_seconds', 0, 'clip_duration_seconds'],
            'overlong story clip' => ['clip_duration_seconds', 61, 'clip_duration_seconds'],
            'forged marks' => ['marks', ['is_admin' => true], 'marks'],
            'unknown privacy' => ['privacy', 'everyone_except_owner', 'privacy'],
        ];
    }

    #[DataProvider('invalidDescriptors')]
    public function test_invalid_descriptors_are_rejected_before_any_outbox_or_r2_work(string $key, mixed $value, string $error): void
    {
        $this->actingAs($this->createUser());
        $payload = $this->payload();
        Arr::set($payload, $key, $value);
        $this->postJson(self::API, $payload)->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('media_publications', 0);
        $this->assertDatabaseCount('media_publication_items', 0);
        $this->assertNoPublishedEntities();
        Bus::assertNothingDispatched();
    }

    public static function invalidVideos(): array
    {
        return [
            'missing duration' => ['duration_seconds', null],
            'zero duration' => ['duration_seconds', 0],
            'negative duration' => ['duration_seconds', -1],
            'excessive duration' => ['duration_seconds', 601],
            'excessive bytes' => ['size', 2049],
            'image mime mismatch' => ['mime', 'image/jpeg'],
        ];
    }

    #[DataProvider('invalidVideos')]
    public function test_video_duration_byte_and_type_limits_are_enforced(string $key, mixed $value): void
    {
        $this->actingAs($this->createUser());
        $payload = $this->payload('post', 'video');
        $payload['items'][0][$key] = $value;
        $this->postJson(self::API, $payload)->assertUnprocessable();
        $this->assertDatabaseCount('media_publications', 0);
    }

    public function test_exact_image_video_byte_and_duration_boundaries_are_accepted(): void
    {
        $this->actingAs($this->createUser());
        $image = $this->payload();
        $image['items'][0]['size'] = 1024;
        $this->postJson(self::API, $image)->assertCreated();
        $video = $this->payload('post', 'video');
        $video['items'][0]['size'] = 2048;
        $video['items'][0]['duration_seconds'] = 600;
        $this->postJson(self::API, $video)->assertCreated();
        $this->assertDatabaseCount('media_publications', 2);
        $this->assertNoPublishedEntities();
        Bus::assertNothingDispatched();
    }

    public function test_item_count_mixed_media_and_duplicate_item_identifiers_are_rejected(): void
    {
        $this->actingAs($this->createUser());
        $payload = $this->payload();
        $duplicate = $payload;
        $duplicate['items'][] = $duplicate['items'][0];
        $this->postJson(self::API, $duplicate)->assertUnprocessable()->assertJsonValidationErrors('items.0.client_uid');

        $photo = $this->payload()['items'][0];
        $video = $this->payload('post', 'video')['items'][0];
        foreach ([[$photo, $video], [$video, array_replace($video, ['client_uid' => (string) Str::uuid()])]] as $items) {
            $this->postJson(self::API, array_replace($payload, ['items' => $items]))->assertUnprocessable()->assertJsonValidationErrors('items');
        }
        $photos = array_map(fn () => $this->payload()['items'][0], range(1, 4));
        $this->postJson(self::API, array_replace($payload, ['items' => $photos]))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->postJson(self::API, array_replace($payload, ['kind' => 'story', 'items' => array_slice($photos, 0, 2)]))
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('media_publications', 0);
    }

    public function test_client_cannot_override_ownership_storage_generation_or_ready_state(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $this->actingAs($owner);
        $payload = $this->payload();
        $payload += ['user_id' => $other->id, 'status' => 'published', 'result' => ['id' => 123], 'profile' => ['video_crf' => 0]];
        $payload['items'][0] += [
            'id' => (string) Str::uuid(), 'generation' => 99, 'status' => 'processed', 'progress' => 100,
            'upload' => ['disk' => 'local', 'path' => '../private'],
            'output' => ['source_path' => 'https://attacker.example.test/original.jpg'],
        ];
        $created = $this->postJson(self::API, $payload)->assertCreated()->assertJsonPath('data.status', 'uploading')
            ->assertJsonPath('data.items.0.status', 'pending')->assertJsonPath('data.items.0.progress', 0)->assertJsonPath('data.result', null)->json('data');
        $publication = MediaPublication::findOrFail($created['id']);
        $item = $publication->items()->firstOrFail();
        $this->assertSame($owner->id, $publication->user_id);
        $this->assertNotSame($payload['items'][0]['id'], $item->id);
        $this->assertSame(0, $item->generation);
        $this->assertNull($item->upload);
        $this->assertNull($item->output);
        $this->assertNotSame(0, $publication->profile['video_crf']);
        $this->assertNoPublishedEntities();
    }

    public function test_chat_membership_and_reply_target_are_checked_at_admission(): void
    {
        $owner = $this->createUser();
        $chat = $this->createChat($this->createUser());
        $this->actingAs($owner);
        $payload = array_replace($this->payload('chat'), ['chat_id' => $chat->chat_id]);
        $this->postJson(self::API, $payload)->assertForbidden();
        $this->postJson(self::API, array_replace($payload, ['chat_id' => (string) Str::uuid()]))->assertForbidden();
        $chat->addParticipant($owner->id);
        $this->postJson(self::API, array_replace($payload, ['parent_id' => 999999]))->assertUnprocessable();
        $this->postJson(self::API, $payload)->assertCreated();
        $this->assertNoPublishedEntities();
    }

    public function test_losing_chat_membership_prevents_resume_and_retry(): void
    {
        $owner = $this->createUser();
        $chat = $this->createChat($owner);
        $this->actingAs($owner);
        $publication = $this->createPublication(array_replace($this->payload('chat'), ['chat_id' => $chat->chat_id]));
        $chat->removeParticipant($owner->id);
        $this->postJson($this->itemUrl($publication, 'resume'))->assertForbidden();
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertForbidden();
        $this->assertSame('pending', $publication->items[0]->refresh()->status);
        Bus::assertNothingDispatched();
    }

    public function test_per_user_capacity_returns_retry_after_but_replays_do_not_consume_capacity(): void
    {
        $this->actingAs($this->createUser());
        $payload = $this->payload();
        $first = $this->createPublication($payload);
        $this->createPublication();
        $this->postJson(self::API, $this->payload())->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->postJson(self::API, $payload)->assertCreated()->assertJsonPath('data.id', $first->id);
        $this->assertDatabaseCount('media_publications', 2);
        $this->assertDatabaseCount('media_publication_items', 2);

        $this->actingAs($this->createUser());
        $this->postJson(self::API, $this->payload())->assertCreated();
        $this->assertDatabaseCount('media_publications', 3);
    }

    public function test_global_video_capacity_does_not_block_images(): void
    {
        config(['media.publications.video_limit' => 1]);
        $this->actingAs($this->createUser());
        $this->createPublication($this->payload('post', 'video'));
        $this->actingAs($this->createUser());
        $this->postJson(self::API, $this->payload('post', 'video'))->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->postJson(self::API, $this->payload())->assertCreated();
        $this->assertDatabaseCount('media_publications', 2);
    }

    public function test_old_processing_queue_applies_video_backpressure_with_only_one_fixture(): void
    {
        $this->actingAs($this->createUser());
        $old = $this->createPublication($this->payload('post', 'video'));
        $old->update(['status' => 'processing']);
        $old->items[0]->forceFill(['status' => 'processing', 'queued_at' => now()->subSeconds(901), 'updated_at' => now()])->save();
        $this->actingAs($this->createUser());
        $this->postJson(self::API, $this->payload('post', 'video'))->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->postJson(self::API, $this->payload())->assertCreated();
    }

    public static function releasedCapacity(): array
    {
        return ['published' => ['published', false], 'cancelled' => ['cancelled', false], 'expired' => ['uploading', true], 'expired failed' => ['failed', true]];
    }

    #[DataProvider('releasedCapacity')]
    public function test_terminal_and_expired_records_do_not_consume_admission_capacity(string $status, bool $expired): void
    {
        config(['media.publications.per_user_limit' => 1, 'media.publications.video_limit' => 1]);
        $this->actingAs($this->createUser());
        $old = $this->createPublication($this->payload('post', 'video'));
        $old->update(['status' => $status, 'expires_at' => $expired ? now()->subMinute() : now()->addDay()]);
        $this->postJson(self::API, $this->payload('post', 'video'))->assertCreated();
        $this->assertDatabaseCount('media_publications', 2);
    }

    public function test_failed_publications_retain_user_and_global_video_capacity_until_cancelled(): void
    {
        config(['media.publications.per_user_limit' => 1, 'media.publications.video_limit' => 1]);
        $owner = $this->createUser();
        $this->actingAs($owner);
        $failed = $this->createPublication($this->payload('post', 'video'));
        $failed->update(['status' => 'failed', 'error' => 'Processing failed.']);
        $this->postJson(self::API, $this->payload())->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->actingAs($this->createUser());
        $this->postJson(self::API, $this->payload('post', 'video'))->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->actingAs($owner);
        $this->deleteJson(self::API.'/'.$failed->id)->assertOk();
        $this->postJson(self::API, $this->payload('post', 'video'))->assertCreated();
    }

    public function test_frozen_account_receives_retry_after_without_admitting_a_publication(): void
    {
        $owner = $this->createUser();
        app(SafetyService::class)->userSafety($owner)->update(['frozen_until' => now()->addMinutes(5)]);
        $this->actingAs($owner);
        $this->postJson(self::API, $this->payload())->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->assertDatabaseCount('media_publications', 0);
    }

    public function test_generation_mismatch_rejects_completion_without_touching_storage_or_queue(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'] + 1, 'parts' => []])
            ->assertConflict();
        $this->assertSame('uploading', $publication->items[0]->refresh()->status);
        $this->assertNull($publication->items[0]->dispatched_at);
        Bus::assertNothingDispatched();
    }

    public static function wrongRawSizes(): array
    {
        return ['missing object' => [null], 'empty object' => [0], 'short object' => [15], 'long object' => [17]];
    }

    #[DataProvider('wrongRawSizes')]
    public function test_raw_completion_requires_the_exact_remote_object_size(?int $size): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        if ($size !== null) {
            Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('x', $size));
        }
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])
            ->assertUnprocessable();
        $this->assertSame('uploading', $publication->refresh()->status);
        $this->assertSame('uploading', $publication->items[0]->refresh()->status);
        $this->assertNull($publication->items[0]->dispatched_at);
        $this->assertNoPublishedEntities();
        Bus::assertNothingDispatched();
    }

    public function test_repeated_raw_completion_queues_processing_once_and_never_creates_public_media(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('x', 16));
        $queuedAt = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])
                ->assertOk()->assertJsonPath('data.status', 'processing')->assertJsonPath('data.result', null)
                ->assertJsonPath('data.items.0.status', 'uploaded')->assertJsonPath('data.items.0.progress', 100);
            $item = $publication->items[0]->refresh();
            if ($attempt === 0) {
                $queuedAt = $item->queued_at;
                $this->travel(1)->minutes();
            } else {
                $this->assertTrue($queuedAt->equalTo($item->queued_at));
            }
        }
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
        Bus::assertDispatched(ProcessPublicationItem::class, fn ($job) => $job->itemId === $publication->items[0]->id
            && $job->generation === $session['generation'] && $job->afterCommit === true);
        Bus::assertNotDispatched(FinalizePublication::class);
        $this->assertNotNull($publication->items[0]->refresh()->dispatched_at);
        $this->assertNoPublishedEntities();
        Storage::disk('r2_temp')->assertExists($session['upload']['path']);
    }

    public function test_multipart_completion_uses_only_the_owned_session_and_queues_once(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $session = $this->startUpload($publication, 'multipart');
        $parts = [['part_number' => 1, 'etag' => '"part-one"'], ['part_number' => 2, 'etag' => '"part-two"']];
        $upload = $session['upload'];
        $this->r2->shouldReceive('completeMultipartUpload')->once()
            ->with($upload['path'], $upload['upload_id'], $parts, 'r2_temp', 2)
            ->andReturnUsing(function () use ($upload) {
                Storage::disk('r2_temp')->put($upload['path'], str_repeat('v', 24));
            });
        $request = ['generation' => $session['generation'], 'parts' => $parts,
            'path' => 'other-users/source.mp4', 'disk' => 'local', 'upload_id' => 'forged-session'];
        $this->postJson($this->itemUrl($publication, 'complete'), $request)->assertOk()->assertJsonPath('data.status', 'processing');
        $this->postJson($this->itemUrl($publication, 'complete'), $request)->assertOk();
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
        $this->assertNoPublishedEntities();
    }

    public static function invalidCompletions(): array
    {
        return [
            'missing generation' => [['parts' => []]],
            'zero generation' => [['generation' => 0, 'parts' => []]],
            'missing parts' => [['generation' => 1]],
            'duplicate parts' => [['generation' => 1, 'parts' => [['part_number' => 1, 'etag' => 'a'], ['part_number' => 1, 'etag' => 'b']]]],
            'zero part number' => [['generation' => 1, 'parts' => [['part_number' => 0, 'etag' => 'a']]]],
            'out of range part' => [['generation' => 1, 'parts' => [['part_number' => 10001, 'etag' => 'a']]]],
            'empty etag' => [['generation' => 1, 'parts' => [['part_number' => 1, 'etag' => '']]]],
        ];
    }

    #[DataProvider('invalidCompletions')]
    public function test_malformed_completion_is_rejected_before_r2_or_processing(array $request): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $this->postJson($this->itemUrl($publication, 'complete'), $request)->assertUnprocessable();
        $this->assertSame('pending', $publication->items[0]->refresh()->status);
        Bus::assertNothingDispatched();
    }

    public function test_multipart_resume_preserves_etags_and_generation_while_refreshing_signed_urls(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $session = $this->startUpload($publication, 'multipart');
        $upload = $session['upload'];
        $refreshed = $upload;
        $refreshed['parts'][0]['upload_url'] = 'https://r2.example.test/part-1?signature=fresh';
        $refreshed['parts'][1]['upload_url'] = 'https://r2.example.test/part-2?signature=fresh';
        $refreshed['expires_at'] = now()->addHours(2)->toIso8601String();
        $this->r2->shouldReceive('listMultipartUploadParts')->once()->with($upload['path'], $upload['upload_id'], 'r2_temp')
            ->andReturn([['PartNumber' => 1, 'ETag' => '"saved-etag"']]);
        $this->r2->shouldReceive('refreshPublicationUpload')->once()->with($upload)->andReturn($refreshed);

        $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()
            ->assertJsonPath('data.generation', $session['generation'])
            ->assertJsonPath('data.upload.upload_id', $upload['upload_id'])
            ->assertJsonPath('data.upload.parts', $refreshed['parts'])
            ->assertJsonPath('data.completed_parts', [['part_number' => 1, 'etag' => '"saved-etag"']])
            ->assertJsonPath('data.upload.upload_concurrency', 2)
            ->assertJsonPath('data.upload.raw_fallback_max_bytes', 0)
            ->assertJsonPath('data.upload.part_fallback_max_bytes', 0);
        $this->assertSame($refreshed, $publication->items[0]->refresh()->upload);
        Bus::assertNothingDispatched();
    }

    public function test_raw_resume_refreshes_the_url_without_replacing_the_object_or_generation(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        $fresh = array_replace($session['upload'], ['upload_url' => 'https://r2.example.test/raw?signature=fresh']);
        $this->r2->shouldReceive('refreshPublicationUpload')->once()->with($session['upload'])->andReturn($fresh);
        $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()
            ->assertJsonPath('data.generation', $session['generation'])->assertJsonPath('data.upload', $fresh)
            ->assertJsonPath('data.completed_parts', []);
    }

    public function test_expired_multipart_session_restarts_with_a_new_generation_and_rejects_old_completion(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $session = $this->startUpload($publication, 'multipart');
        $upload = $session['upload'];
        $this->r2->shouldReceive('listMultipartUploadParts')->once()->with($upload['path'], $upload['upload_id'], 'r2_temp')
            ->andThrow($this->s3Error('NoSuchUpload'));
        $replacement = $this->uploadDescriptor($publication->items[0], 'multipart');
        $this->r2->shouldReceive('createVideoUpload')->once()->andReturn($replacement);

        $resumed = $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()
            ->assertJsonPath('data.generation', $session['generation'] + 1)
            ->assertJsonPath('data.upload.upload_id', $replacement['upload_id'])
            ->assertJsonPath('data.completed_parts', [])->json('data');
        $this->assertNotSame($upload['path'], $resumed['upload']['path']);
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])->assertConflict();
        Bus::assertNothingDispatched();
    }

    public function test_access_denied_is_not_swallowed_as_an_expired_multipart_upload(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $session = $this->startUpload($publication, 'multipart');
        $item = $publication->items[0]->refresh();
        $before = $item->getAttributes();
        $error = $this->s3Error('AccessDenied');
        $this->r2->shouldReceive('listMultipartUploadParts')->once()
            ->with($session['upload']['path'], $session['upload']['upload_id'], 'r2_temp')->andThrow($error);
        $this->withoutExceptionHandling();
        try {
            $this->postJson($this->itemUrl($publication, 'resume'));
            $this->fail('AccessDenied must not be converted into a replacement upload.');
        } catch (S3Exception $caught) {
            $this->assertSame($error, $caught);
        }
        $this->assertSame($before, $item->refresh()->getAttributes());
        Bus::assertNothingDispatched();
    }

    public static function readyItemStates(): array
    {
        return ['uploaded' => ['uploaded'], 'processing' => ['processing'], 'processed' => ['processed']];
    }

    #[DataProvider('readyItemStates')]
    public function test_ready_items_need_no_new_upload_session(string $status): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $publication->items[0]->update(['status' => $status, 'generation' => 3, 'progress' => 100]);
        $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()
            ->assertJsonPath('data.status', $status)->assertJsonPath('data.generation', 3)
            ->assertJsonPath('data.upload', null)->assertJsonPath('data.completed_parts', []);
        Bus::assertNothingDispatched();
    }

    public function test_raw_upload_found_after_a_lost_response_can_be_completed_without_reuploading(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('x', 16));
        $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()
            ->assertJsonPath('data.status', 'uploaded')->assertJsonPath('data.upload', null)
            ->assertJsonPath('data.generation', $session['generation']);
        Bus::assertNothingDispatched();
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])->assertOk();
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
    }

    public function test_existing_publications_can_resume_complete_retry_and_cancel_after_rollout_is_disabled(): void
    {
        $owner = $this->createUser();
        $this->actingAs($owner);
        $payload = $this->payload();
        $first = $this->createPublication($payload);
        $second = $this->createPublication();
        config(['media.publications.enabled' => false, 'media.publications.kinds' => [], 'media.publications.allowed_user_ids' => [$owner->id + 1]]);
        $this->postJson(self::API, $this->payload())->assertStatus(503);
        $this->postJson(self::API, $payload)->assertCreated()->assertJsonPath('data.id', $first->id);
        $this->getJson(self::API.'/'.$first->id)->assertOk();
        $this->getJson(self::API)->assertOk()->assertJsonCount(2, 'data');

        $session = $this->startUpload($first);
        Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('x', 16));
        $this->postJson($this->itemUrl($first, 'complete'), ['generation' => $session['generation'], 'parts' => []])->assertOk();
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
        $second->update(['status' => 'failed', 'error' => 'Processing failed.']);
        $this->postJson(self::API.'/'.$second->id.'/retry')->assertOk()->assertJsonPath('data.error', null);
        $this->deleteJson(self::API.'/'.$second->id)->assertOk()->assertJsonPath('data.status', 'cancelled');
        Bus::assertDispatchedTimes(CleanupPublication::class, 1);
        $this->assertNoPublishedEntities();
    }

    public function test_cancellation_blocks_resume_completion_and_retry_and_queues_cleanup(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('x', 16));
        $this->deleteJson(self::API.'/'.$publication->id)->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson($this->itemUrl($publication, 'resume'))->assertConflict();
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])->assertConflict();
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertConflict();
        Bus::assertDispatchedTimes(CleanupPublication::class, 1);
        Bus::assertNotDispatched(ProcessPublicationItem::class);
        $this->assertNoPublishedEntities();
    }

    public function test_expired_publication_requires_retry_before_resume_or_completion(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $session = $this->startUpload($publication);
        $publication->update(['expires_at' => now()->subMinute()]);
        $this->postJson($this->itemUrl($publication, 'resume'))->assertStatus(410);
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])->assertStatus(410);
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertOk()->assertJsonPath('data.error', null);
        $this->assertTrue($publication->refresh()->expires_at->isFuture());
        $this->r2->shouldReceive('refreshPublicationUpload')->once()->andReturn($session['upload']);
        $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()->assertJsonPath('data.generation', $session['generation']);
        Bus::assertNotDispatched(ProcessPublicationItem::class);
    }

    public function test_retry_of_failed_work_obeys_capacity_without_mutating_the_failed_record(): void
    {
        $this->actingAs($this->createUser());
        $failed = $this->createPublication();
        $failed->update(['status' => 'failed', 'error' => 'Processing failed.']);
        $before = $failed->getAttributes();
        $this->createPublication();
        config(['media.publications.per_user_limit' => 1]);
        $this->postJson(self::API.'/'.$failed->id.'/retry')->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->assertSame($before, $failed->refresh()->getAttributes());
        Bus::assertNothingDispatched();
    }

    public function test_retry_of_a_failed_publication_does_not_count_itself_against_capacity(): void
    {
        config(['media.publications.per_user_limit' => 1, 'media.publications.video_limit' => 1]);
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $publication->update(['status' => 'failed', 'error' => 'Processing failed.']);
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertOk()->assertJsonPath('data.error', null);
        $this->assertDatabaseCount('media_publications', 1);
    }

    public static function expiredRetryQuotas(): array
    {
        return ['user capacity' => [false], 'global video capacity' => [true]];
    }

    #[DataProvider('expiredRetryQuotas')]
    public function test_retry_of_an_expired_publication_must_reacquire_admission_capacity(bool $global): void
    {
        config(['media.publications.per_user_limit' => 1, 'media.publications.video_limit' => 1]);
        $owner = $this->createUser();
        $this->actingAs($owner);
        $payload = $this->payload('post', $global ? 'video' : 'image');
        $expired = $this->createPublication($payload);
        $expired->update(['expires_at' => now()->subMinute()]);
        $before = $expired->getAttributes();
        if ($global) {
            $this->actingAs($this->createUser());
        }
        $this->createPublication($this->payload('post', $global ? 'video' : 'image'));
        $this->actingAs($owner);

        $this->postJson(self::API.'/'.$expired->id.'/retry')->assertStatus(429)->assertHeader('Retry-After', '60');
        $this->assertSame($before, $expired->refresh()->getAttributes());
        Bus::assertNothingDispatched();
    }

    public function test_retry_of_queued_work_preserves_the_original_queue_age(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $session = $this->startUpload($publication);
        Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('v', 24));
        $this->postJson($this->itemUrl($publication, 'complete'), ['generation' => $session['generation'], 'parts' => []])->assertOk();
        $queuedAt = $publication->items[0]->refresh()->queued_at;
        $this->travel(16)->minutes();
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertOk();
        $this->assertTrue($queuedAt->equalTo($publication->items[0]->refresh()->queued_at), 'Retry must not hide old queued work from admission backpressure.');

        $this->actingAs($this->createUser());
        $this->postJson(self::API, $this->payload('post', 'video'))->assertStatus(429)->assertHeader('Retry-After', '60');
    }

    public function test_retry_reuses_uploaded_bytes_and_leaves_processed_items_intact(): void
    {
        $this->actingAs($this->createUser());
        $payload = $this->payload();
        $payload['items'][] = $this->payload()['items'][0];
        $publication = $this->createPublication($payload);
        $session = $this->startUpload($publication);
        Storage::disk('r2_temp')->put($session['upload']['path'], str_repeat('x', 16));
        $publication->update(['status' => 'failed', 'error' => 'Worker interrupted.']);
        $publication->items[0]->update(['status' => 'failed']);
        $processed = $publication->items[1];
        $processed->update(['status' => 'processed', 'generation' => 1, 'output' => ['disk' => 'r2_final', 'source_path' => 'optimized/photo.webp']]);
        $before = $processed->getAttributes();
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertOk()->assertJsonPath('data.error', null);
        $this->assertSame('uploaded', $publication->items[0]->refresh()->status);
        $this->assertSame($before, $processed->refresh()->getAttributes());
        Bus::assertDispatchedTimes(ProcessPublicationItem::class, 1);
        Bus::assertDispatchedTimes(FinalizePublication::class, 1);
        Storage::disk('r2_temp')->assertExists($session['upload']['path']);
        $this->assertNoPublishedEntities();
    }

    public function test_published_results_cannot_be_retried_or_cancelled_through_the_outbox(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication();
        $result = ['id' => 123, 'type' => 'post', 'url' => '/posts/published'];
        $publication->update(['status' => 'published', 'result' => $result, 'published_at' => now()]);
        $this->postJson(self::API.'/'.$publication->id.'/retry')->assertConflict();
        $this->deleteJson(self::API.'/'.$publication->id)->assertConflict();
        $this->getJson(self::API.'/'.$publication->id)->assertOk()->assertJsonPath('data.result', $result);
        Bus::assertNothingDispatched();
    }

    public function test_list_and_status_expose_progress_without_signed_urls_or_private_storage_paths(): void
    {
        $this->actingAs($this->createUser());
        $publication = $this->createPublication($this->payload('post', 'video'));
        $session = $this->startUpload($publication, 'multipart');
        $publication->items[0]->update(['progress' => 37, 'output' => ['disk' => 'r2_final', 'source_path' => 'private/optimized/secret.mp4']]);
        $show = $this->getJson(self::API.'/'.$publication->id)->assertOk()->assertJsonPath('data.items.0.progress', 37)->json('data');
        $list = $this->getJson(self::API)->assertOk()->assertJsonCount(1, 'data')->json('data.0');
        foreach ([$show, $list] as $data) {
            $this->assertEqualsCanonicalizing(['id', 'client_uid', 'kind', 'status', 'content', 'chat_id', 'error', 'result', 'created_at'], array_keys(Arr::except($data, 'items')));
            $this->assertEqualsCanonicalizing(['id', 'client_uid', 'type', 'size', 'status', 'progress'], array_keys($data['items'][0]));
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            foreach ([$session['upload']['path'], $session['upload']['upload_id'], $session['upload']['parts'][0]['upload_url'], 'private/optimized/secret.mp4', 'r2_temp', 'r2_final'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
    }

    private function createPublication(?array $payload = null): MediaPublication
    {
        $id = $this->postJson(self::API, $payload ?? $this->payload())->assertCreated()->json('data.id');

        return MediaPublication::with('items')->findOrFail($id);
    }

    private function payload(string $kind = 'post', string $type = 'image'): array
    {
        $item = [
            'client_uid' => (string) Str::uuid(), 'type' => $type,
            'name' => $type === 'video' ? 'clip.mp4' : 'photo.jpg',
            'mime' => $type === 'video' ? 'video/mp4' : 'image/jpeg',
            'size' => $type === 'video' ? 24 : 16,
        ];
        if ($type === 'video') {
            $item['duration_seconds'] = 10;
        }

        return [
            'client_uid' => (string) Str::uuid(), 'kind' => $kind, 'content' => 'Selected locally',
            'privacy' => 'all', 'selected_user_ids' => [], 'marks' => [], 'clip_start_seconds' => 0,
            'items' => [$item],
        ];
    }

    private function startUpload(MediaPublication $publication, string $type = 'raw'): array
    {
        $item = $publication->items[0];
        $upload = $this->uploadDescriptor($item, $type);
        $this->r2->shouldReceive('createVideoUpload')->once()->with([
            'mime' => $item->mime, 'size' => $item->size, 'extension' => pathinfo($item->name, PATHINFO_EXTENSION),
        ])->andReturn($upload);

        return $this->postJson($this->itemUrl($publication, 'resume'))->assertOk()
            ->assertJsonPath('data.status', 'uploading')->assertJsonPath('data.generation', 1)
            ->assertJsonPath('data.upload.upload_concurrency', 2)
            ->assertJsonPath('data.upload.raw_fallback_max_bytes', 0)
            ->assertJsonPath('data.upload.part_fallback_max_bytes', 0)->json('data');
    }

    private function uploadDescriptor(MediaPublicationItem $item, string $type): array
    {
        $uid = (string) Str::uuid();
        $path = 'tmp/direct/publications/'.$uid.'.'.pathinfo($item->name, PATHINFO_EXTENSION);
        $upload = [
            'provider' => 'r2_direct', 'uid' => $path, 'path' => $path,
            'disk' => 'r2_temp', 'upload_disk' => 'r2_temp', 'final_disk' => 'r2_final',
            'upload_url' => $type === 'raw' ? 'https://r2.example.test/'.$uid.'?signature=original' : null,
            'upload_method' => 'PUT', 'upload_type' => $type,
            'upload_headers' => ['Content-Type' => $item->mime],
            'upload_concurrency' => 3, 'raw_fallback_max_bytes' => 1000, 'part_fallback_max_bytes' => 1000,
            'expires_at' => now()->addHour()->toIso8601String(),
        ];
        if ($type === 'multipart') {
            $midpoint = intdiv($item->size, 2);
            $upload['upload_id'] = 'upload-'.$uid;
            $upload['part_size'] = $midpoint;
            $upload['parts'] = array_map(fn ($number, $start, $end) => [
                'part_number' => $number, 'start' => $start, 'end' => $end,
                'upload_url' => 'https://r2.example.test/'.$uid.'/part-'.$number.'?signature=original',
                'upload_method' => 'PUT', 'upload_headers' => [],
            ], [1, 2], [0, $midpoint], [$midpoint, $item->size]);
        }

        return $upload;
    }

    private function itemUrl(MediaPublication $publication, string $action): string
    {
        return self::API.'/'.$publication->id.'/items/'.$publication->items[0]->id.'/'.$action;
    }

    private function s3Error(string $code): S3Exception
    {
        return new S3Exception($code, new Command('ListParts'), ['code' => $code]);
    }

    private function assertNoPublishedEntities(): void
    {
        foreach (['posts', 'stories', 'story_frames', 'messages', 'media'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Storage::disk('r2_final')->assertDirectoryEmpty('/');
    }

    private function createChat(User $owner): Chat
    {
        $chat = Chat::create(['chat_id' => (string) Str::uuid(), 'type' => ChatType::DIRECT, 'created_at' => now()]);
        $chat->addParticipant($owner->id);

        return $chat;
    }

    private function createUser(): User
    {
        $username = 'publication-'.Str::lower(Str::random(10));

        return User::query()->create([
            'first_name' => 'Publication', 'last_name' => 'Tester', 'username' => $username,
            'caption' => '@'.$username, 'email' => $username.'@example.test',
            'phone' => '', 'website' => '', 'bio' => '', 'country' => null, 'city' => null,
            'birth_day' => null, 'birth_month' => null, 'birth_year' => null, 'age' => null,
            'gender' => 'male', 'last_active' => now()->timestamp, 'language' => 'en',
            'avatar' => null, 'cover' => null, 'verified' => false, 'tips' => [],
            'email_verified_at' => now(), 'password' => Hash::make('password'), 'role' => 'user',
            'theme' => 'light', 'publications_count' => 0, 'followers_count' => 0, 'following_count' => 0,
            'status' => UserStatus::ACTIVE, 'type' => 'author',
        ]);
    }
}
