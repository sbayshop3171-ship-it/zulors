<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Models\User;
use App\Services\TestContent\TestAccountImagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TestAccountImagePublisherTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDirectory = storage_path('framework/testing/test-image-import-' . uniqid());
        File::ensureDirectoryExists($this->sourceDirectory . '/one');
        File::ensureDirectoryExists($this->sourceDirectory . '/two');
        File::put($this->sourceDirectory . '/one/second.webp', 'test');
        File::put($this->sourceDirectory . '/two/first.jpg', 'test');
        File::put($this->sourceDirectory . '/ignored.txt', 'test');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceDirectory);

        parent::tearDown();
    }

    public function test_preview_only_targets_active_test_accounts_and_supported_images(): void
    {
        $first = $this->createUser('image-test-one', 'image-test-one@gmail.test');
        $second = $this->createUser('image-test-two', 'image-test-two@gmail.test');
        $this->createUser('image-real-user', 'image-real@example.com');
        $this->createUser('image-inactive-test', 'image-inactive@gmail.test', UserStatus::BLOCKED);

        $preview = app(TestAccountImagePublisher::class)->preview($this->sourceDirectory);

        $this->assertSame(2, $preview['source_count']);
        $this->assertSame(2, $preview['eligible_count']);
        $this->assertSame([$first->id, $second->id], $preview['user_ids']);
        $this->assertCount(2, $preview['source_files']);
        $this->assertStringEndsWith('one/second.webp', $preview['source_files'][0]);
        $this->assertStringEndsWith('two/first.jpg', $preview['source_files'][1]);
    }

    public function test_multiple_source_directories_are_merged_in_order_without_duplicates(): void
    {
        $this->createUser('gallery-test-one', 'gallery-test-one@gmail.test');
        $this->createUser('gallery-test-two', 'gallery-test-two@gmail.test');
        $this->createUser('gallery-test-three', 'gallery-test-three@gmail.test');

        $secondDirectory = storage_path('framework/testing/test-image-import-second-' . uniqid());
        File::ensureDirectoryExists($secondDirectory);
        File::put($secondDirectory . '/third.png', 'test');

        try {
            $preview = app(TestAccountImagePublisher::class)->previewForDirectories([
                $this->sourceDirectory,
                $secondDirectory,
                $this->sourceDirectory,
            ]);

            $this->assertSame(3, $preview['source_count']);
            $this->assertCount(3, $preview['user_ids']);
            $this->assertStringEndsWith('one/second.webp', $preview['source_files'][0]);
            $this->assertStringEndsWith('two/first.jpg', $preview['source_files'][1]);
            $this->assertStringEndsWith('third.png', $preview['source_files'][2]);
        } finally {
            File::deleteDirectory($secondDirectory);
        }
    }

    public function test_command_dry_run_requires_explicit_confirmation_before_writing(): void
    {
        $this->createUser('image-command-one', 'image-command-one@gmail.test');

        $this->artisan("test-content:publish-images --source={$this->sourceDirectory} --limit=1 --dry-run")
            ->expectsOutput('Image posts targeted in this run: 1')
            ->expectsOutput('Dry run complete. No posts or media were created.')
            ->assertExitCode(0);

        $this->artisan("test-content:publish-images --source={$this->sourceDirectory} --limit=1")
            ->expectsOutput('Nothing was published. Re-run with --confirm=ALL_TEST_IMAGE_POSTS after checking the target count.')
            ->assertExitCode(1);
    }

    private function createUser(string $username, string $email, UserStatus $status = UserStatus::ACTIVE): User
    {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Account',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => $email,
            'phone' => '',
            'website' => '',
            'bio' => '',
            'country' => null,
            'city' => null,
            'birth_day' => null,
            'birth_month' => null,
            'birth_year' => null,
            'age' => null,
            'gender' => 'male',
            'last_active' => now()->timestamp,
            'language' => 'en',
            'avatar' => null,
            'cover' => null,
            'verified' => false,
            'tips' => [],
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'user',
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => $status,
            'type' => 'author',
        ]);
    }
}
