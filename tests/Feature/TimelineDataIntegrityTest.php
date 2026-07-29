<?php

namespace Tests\Feature;

use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType as MediaKind;
use App\Enums\Media\MediaVisibility;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\FollowStatus;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Models\Block;
use App\Models\Follow;
use App\Models\Mute;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimelineDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_post_creation_still_publishes_an_active_post(): void
    {
        $author = $this->createUser('post-create-author');

        $this->actingAs($author)
            ->withoutMiddleware()
            ->postJson('/api/post/editor/create', [
                'content' => 'Phase zero smoke post',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Phase zero smoke post');

        $this->assertDatabaseHas('posts', [
            'user_id' => $author->id,
            'content' => 'Phase zero smoke post',
            'status' => PostStatus::ACTIVE->value,
            'type' => PostType::TEXT->value,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $author->id,
            'publications_count' => 1,
        ]);
    }

    public function test_feed_excludes_muted_and_blocked_users_in_for_you_and_latest_modes(): void
    {
        $viewer = $this->createUser('viewer');
        $visibleAuthor = $this->createUser('visible-author');
        $mutedAuthor = $this->createUser('muted-author');
        $blockedAuthor = $this->createUser('blocked-author');

        $visiblePost = $this->createPost($visibleAuthor, 'Visible post', now());
        $mutedPost = $this->createPost($mutedAuthor, 'Muted post', now()->addMinute());
        $blockedPost = $this->createPost($blockedAuthor, 'Blocked post', now()->addMinutes(2));

        Mute::query()->create([
            'muter_id' => $viewer->id,
            'muted_id' => $mutedAuthor->id,
        ]);

        Block::query()->create([
            'blocker_id' => $viewer->id,
            'blocked_id' => $blockedAuthor->id,
        ]);

        $this->assertFeedContainsOnly($viewer, 'for_you', [$visiblePost->id], [$mutedPost->id, $blockedPost->id]);
        $this->assertFeedContainsOnly($viewer, 'latest', [$visiblePost->id], [$mutedPost->id, $blockedPost->id]);
    }

    public function test_bookmark_endpoint_keeps_post_bookmarks_count_in_sync(): void
    {
        $viewer = $this->createUser('bookmark-viewer');
        $author = $this->createUser('bookmark-author');
        $post = $this->createPost($author, 'Bookmarkable post');

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/post/bookmarks/add', ['id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.bookmarked', true);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'bookmarks_count' => 1,
        ]);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/post/bookmarks/add', ['id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.bookmarked', false);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'bookmarks_count' => 0,
        ]);
    }

    public function test_share_endpoint_increments_post_shares_count(): void
    {
        $viewer = $this->createUser('share-viewer');
        $author = $this->createUser('share-author');
        $post = $this->createPost($author, 'Shareable post');

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/post/share/add', ['id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.shares_count.raw', 1);

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->postJson('/api/timeline/post/share/add', ['id' => $post->id])
            ->assertOk()
            ->assertJsonPath('data.shares_count.raw', 2);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'shares_count' => 2,
        ]);
    }

    public function test_owner_can_edit_post_text_and_topics_without_html(): void
    {
        $author = $this->createUser('edit-owner');
        $post = $this->createPost($author, 'Original #oldtopic', null, [
            'text_language' => 'bn',
        ]);

        app(\App\Services\Timeline\TopicExtractionService::class)->syncPostTopics($post);

        $this->actingAs($author)
            ->withoutMiddleware()
            ->putJson('/api/timeline/post/update', [
                'id' => $post->id,
                'content' => ' Updated <b>caption</b> #NewTopic ',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Updated caption #NewTopic')
            ->assertJsonPath('data.meta.is_edited', true)
            ->assertJsonPath('data.meta.permissions.can_edit', true);

        $post->refresh();

        $this->assertSame('Updated caption #NewTopic', $post->content);
        $this->assertTrue($post->edited);
        $this->assertNotSame('bn', $post->text_language);

        $this->assertDatabaseHas('post_topics', [
            'post_id' => $post->id,
            'topic' => 'newtopic',
        ]);

        $this->assertDatabaseMissing('post_topics', [
            'post_id' => $post->id,
            'topic' => 'oldtopic',
        ]);
    }

    public function test_authenticated_owner_can_edit_post_text_with_sanctum_middleware(): void
    {
        $author = $this->createUser('sanctum-edit-owner');
        $post = $this->createPost($author, 'Original authenticated content');

        Sanctum::actingAs($author);

        $this->putJson('/api/timeline/post/update', [
            'id' => $post->id,
            'content' => 'Authenticated edit content',
        ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Authenticated edit content')
            ->assertJsonPath('data.meta.permissions.can_edit', true);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'Authenticated edit content',
            'edited' => true,
        ]);
    }

    public function test_non_owner_cannot_edit_post_text(): void
    {
        $author = $this->createUser('edit-author');
        $viewer = $this->createUser('edit-viewer');
        $post = $this->createPost($author, 'Owner text');

        $this->actingAs($viewer)
            ->withoutMiddleware()
            ->putJson('/api/timeline/post/update', [
                'id' => $post->id,
                'content' => 'Viewer edit attempt',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'Owner text',
            'edited' => false,
        ]);
    }

    public function test_admin_can_edit_any_post_text(): void
    {
        $author = $this->createUser('admin-edit-author');
        $admin = $this->createUser('timeline-admin', role: UserRole::ADMIN);
        $post = $this->createPost($author, 'Needs admin correction');

        $this->actingAs($admin)
            ->withoutMiddleware()
            ->putJson('/api/timeline/post/update', [
                'id' => $post->id,
                'content' => 'Admin corrected text',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', 'Admin corrected text')
            ->assertJsonPath('data.meta.permissions.can_edit', true);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'Admin corrected text',
            'edited' => true,
        ]);
    }

    public function test_media_post_caption_edit_does_not_touch_media_rows(): void
    {
        $author = $this->createUser('media-edit-owner');
        $post = $this->createPost($author, 'Image caption', null, [
            'type' => PostType::IMAGE,
        ]);

        $media = $this->createImageMedia($post);
        $originalMedia = $media->only([
            'source_path',
            'thumbnail_path',
            'type',
            'status',
            'disk',
            'thumbnail_disk',
            'extension',
            'visibility',
            'mime',
            'size',
            'thumbnail_size',
            'order',
            'metadata',
        ]);

        $this->actingAs($author)
            ->withoutMiddleware()
            ->putJson('/api/timeline/post/update', [
                'id' => $post->id,
                'content' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.content', '')
            ->assertJsonPath('data.meta.is_edited', true)
            ->assertJsonCount(1, 'data.relations.media');

        $media->refresh();

        $this->assertSame('', $post->refresh()->content);
        $this->assertSame($originalMedia, $media->only(array_keys($originalMedia)));
    }

    public function test_deleted_post_cannot_be_edited(): void
    {
        $author = $this->createUser('deleted-edit-owner');
        $post = $this->createPost($author, 'Deleted text', null, [
            'status' => PostStatus::DELETED,
        ]);

        $this->actingAs($author)
            ->withoutMiddleware()
            ->putJson('/api/timeline/post/update', [
                'id' => $post->id,
                'content' => 'Should not update',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'content' => 'Deleted text',
            'edited' => false,
        ]);
    }

    public function test_latest_feed_is_ordered_by_time_only(): void
    {
        $viewer = $this->createUser('latest-viewer');
        $author = $this->createUser('latest-author');

        $oldEngagedPost = $this->createPost($author, 'Old but engaged', now()->subHour(), [
            'comments_count' => 100,
            'bookmarks_count' => 100,
            'views_count' => 1000,
            'quotes_count' => 20,
        ]);

        $newPost = $this->createPost($author, 'Newest post', now());

        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson('/api/timeline/feed?type=latest')
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        $this->assertSame($newPost->id, $postIds[0]);
        $this->assertSame($oldEngagedPost->id, $postIds[1]);
    }

    public function test_following_feed_only_returns_followed_author_posts(): void
    {
        $viewer = $this->createUser('following-viewer');
        $followedAuthor = $this->createUser('followed-author');
        $otherAuthor = $this->createUser('other-author');

        $followedPost = $this->createPost($followedAuthor, 'Followed post');
        $otherPost = $this->createPost($otherAuthor, 'Other post');

        Follow::query()->create([
            'follower_id' => $viewer->id,
            'following_id' => $followedAuthor->id,
            'status' => FollowStatus::FOLLOWING->value,
        ]);

        $this->assertFeedContainsOnly($viewer, 'following', [$followedPost->id], [$otherPost->id]);
    }

    private function assertFeedContainsOnly(User $viewer, string $type, array $expectedIds, array $missingIds): void
    {
        $response = $this->actingAs($viewer)
            ->withoutMiddleware()
            ->getJson("/api/timeline/feed?type={$type}")
            ->assertOk();

        $postIds = array_column($response->json('data'), 'id');

        foreach($expectedIds as $postId) {
            $this->assertContains($postId, $postIds);
        }

        foreach($missingIds as $postId) {
            $this->assertNotContains($postId, $postIds);
        }
    }

    private function createUser(string $username, UserType $type = UserType::AUTHOR, UserRole $role = UserRole::USER): User
    {
        return User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => $username,
            'caption' => '@' . $username,
            'email' => "{$username}@example.com",
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
            'role' => $role,
            'theme' => 'light',
            'publications_count' => 0,
            'followers_count' => 0,
            'following_count' => 0,
            'status' => UserStatus::ACTIVE,
            'type' => $type,
        ]);
    }

    private function createPost(User $author, string $content, $createdAt = null, array $overrides = []): Post
    {
        $createdAt = $createdAt ?: now();

        return Post::query()->create(array_merge([
            'user_id' => $author->id,
            'quote_post_id' => null,
            'title' => '',
            'content' => $content,
            'status' => PostStatus::ACTIVE,
            'type' => PostType::TEXT,
            'text_language' => 'en',
            'edited' => false,
            'profile_pinned' => false,
            'global_pinned' => false,
            'is_sensitive' => false,
            'is_ai_generated' => false,
            'views_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
            'bookmarks_count' => 0,
            'quotes_count' => 0,
            'preview_lqip_base64' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ], $overrides));
    }

    private function createImageMedia(Post $post)
    {
        return $post->media()->create([
            'source_path' => "posts/images/{$post->id}.jpg",
            'thumbnail_path' => '',
            'type' => MediaKind::IMAGE,
            'status' => MediaStatus::PROCESSED,
            'disk' => 'public',
            'thumbnail_disk' => 'public',
            'extension' => 'jpg',
            'visibility' => MediaVisibility::VISIBLE,
            'mime' => 'image/jpeg',
            'size' => '100',
            'thumbnail_size' => '',
            'order' => 0,
            'metadata' => [
                'fixture' => true,
            ],
        ]);
    }
}
