<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Models\User;
use App\Services\Media\Cloudflare\R2DirectUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class DirectAssetUploadApiTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $r2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('r2_temp');
        Storage::fake('r2_final');
        Cache::flush();
        $this->r2 = $this->mock(R2DirectUploadService::class);
        $this->r2->shouldReceive('isConfigured')->andReturnTrue()->byDefault();
    }

    public function test_profile_asset_create_is_idempotent_and_completion_publishes_once(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $clientUid = (string) Str::uuid();
        $upload = $this->rawUpload('tmp/direct/media/profile_avatar/1/image.jpg');
        $this->r2->shouldReceive('createScopedObjectUpload')->once()
            ->with(\Mockery::on(fn ($data) => $data['client_uid'] === $clientUid), 'profile_avatar')
            ->andReturn($upload);
        $this->r2->shouldReceive('refreshPublicationUpload')->once()->with($upload)->andReturn($upload);

        $payload = [
            'operation' => 'profile_avatar', 'client_uid' => $clientUid,
            'name' => 'photo.jpg', 'size' => 5, 'mime' => 'image/jpeg', 'extension' => 'jpg',
        ];
        $first = $this->postJson('/api/media-uploads/create', $payload)->assertOk();
        $second = $this->postJson('/api/media-uploads/create', $payload)->assertOk();
        $this->assertSame($first->json('data.uid'), $second->json('data.uid'));

        $this->r2->shouldReceive('verifyUploadedObject')->once()
            ->with($upload['path'], 'r2_temp', 5, 12 * 1024 * 1024)
            ->andReturn(['size' => 5, 'mime' => 'image/jpeg']);
        $published = ['disk' => 'r2_final', 'path' => 'uploads/users/avatars/one.jpg', 'size' => 5];
        $this->r2->shouldReceive('publishUploadedObject')->once()
            ->with($upload['path'], 'uploads/users/avatars', 'jpg', 'image/jpeg', 'r2_temp')
            ->andReturn($published);

        $completion = ['operation' => 'profile_avatar', 'uid' => $upload['uid']];
        $result = $this->postJson('/api/media-uploads/complete', $completion)->assertOk();
        $this->postJson('/api/media-uploads/complete', $completion)->assertOk()->assertExactJson($result->json());
        $this->assertSame('uploads/users/avatars/one.jpg', $user->refresh()->avatar);
        $this->assertSame('r2_final', $user->avatar_disk);
    }

    public function test_large_profile_image_completes_owned_r2_multipart_session(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $clientUid = (string) Str::uuid();
        $upload = $this->rawUpload('tmp/direct/media/profile_cover/1/cover.jpg');
        $upload['upload_type'] = 'multipart';
        $upload['upload_url'] = null;
        $upload['upload_id'] = 'owned-upload-id';
        $upload['parts'] = [
            ['part_number' => 1, 'start' => 0, 'end' => 5, 'upload_url' => 'https://r2.example.test/part/1'],
            ['part_number' => 2, 'start' => 5, 'end' => 12, 'upload_url' => 'https://r2.example.test/part/2'],
        ];
        $this->r2->shouldReceive('createScopedObjectUpload')->once()->andReturn($upload);
        $this->postJson('/api/media-uploads/create', [
            'operation' => 'profile_cover', 'client_uid' => $clientUid,
            'name' => 'cover.jpg', 'size' => 12, 'mime' => 'image/jpeg', 'extension' => 'jpg',
        ])->assertOk()->assertJsonPath('data.upload_type', 'multipart')->assertJsonCount(2, 'data.parts');

        $parts = [
            ['part_number' => 1, 'etag' => 'part-one'],
            ['part_number' => 2, 'etag' => 'part-two'],
        ];
        $this->r2->shouldReceive('completeMultipartUpload')->once()
            ->with($upload['path'], 'owned-upload-id', $parts, 'r2_temp', 2);
        $this->r2->shouldReceive('verifyUploadedObject')->once()
            ->with($upload['path'], 'r2_temp', 12, 12 * 1024 * 1024)
            ->andReturn(['size' => 12, 'mime' => 'image/jpeg']);
        $this->r2->shouldReceive('publishUploadedObject')->once()->andReturn([
            'disk' => 'r2_final', 'path' => 'uploads/users/covers/one.jpg', 'size' => 12,
        ]);

        $this->postJson('/api/media-uploads/complete', [
            'operation' => 'profile_cover', 'uid' => $upload['uid'], 'parts' => $parts,
        ])->assertOk();
        $this->assertSame('uploads/users/covers/one.jpg', $user->refresh()->cover);
        $this->assertSame('r2_final', $user->cover_disk);
    }

    public function test_asset_identity_cannot_be_reused_for_different_file_metadata(): void
    {
        $this->actingAs($this->user());
        $clientUid = (string) Str::uuid();
        $this->r2->shouldReceive('createScopedObjectUpload')->once()->andReturn($this->rawUpload('tmp/direct/media/profile_avatar/1/one.jpg'));
        $payload = [
            'operation' => 'profile_avatar', 'client_uid' => $clientUid,
            'name' => 'one.jpg', 'size' => 5, 'mime' => 'image/jpeg', 'extension' => 'jpg',
        ];
        $this->postJson('/api/media-uploads/create', $payload)->assertOk();
        $payload['size'] = 6;
        $this->postJson('/api/media-uploads/create', $payload)->assertStatus(409);
    }

    private function rawUpload(string $path): array
    {
        return [
            'uid' => $path, 'path' => $path, 'disk' => 'r2_temp', 'upload_disk' => 'r2_temp',
            'upload_url' => 'https://r2.example.test/upload', 'upload_method' => 'PUT',
            'upload_type' => 'raw', 'upload_headers' => ['Content-Type' => 'image/jpeg'],
            'upload_concurrency' => 2, 'expires_at' => now()->addHour()->toIso8601String(),
        ];
    }

    private function user(): User
    {
        $username = 'asset-'.Str::lower(Str::random(10));
        return User::query()->create([
            'first_name' => 'Asset', 'last_name' => 'Tester', 'username' => $username,
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
