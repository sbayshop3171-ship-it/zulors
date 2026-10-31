<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Enums\User\UserStatus;
use App\Enums\User\UserType;
use App\Enums\User\UserRole;
use App\Models\Comment;
use App\Models\Media;
use App\Models\MediaPublication;
use App\Models\MediaPublicationItem;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Services\Media\Publication\PublicationService;
use Tests\TestCase;

class CommentThreadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_replies_share_one_root_and_reply_pages_are_chronological(): void
    {
        Notification::fake();
        $author = $this->user('thread-author');
        $viewer = $this->user('thread-viewer');
        $post = $this->createPostFixture($author);

        $rootResponse = $this->actingAs($viewer)->withoutMiddleware()->postJson('/api/timeline/post/comment/create', [
            'post_id' => $post->id, 'content' => 'Root comment',
        ])->assertOk()->assertJsonPath('data.post.comments_count.raw', 1);
        $this->assertIsInt($rootResponse->json('data.post.comments_count.raw'));
        $root = $rootResponse->json('data.comment');
        $reply = $this->actingAs($author)->withoutMiddleware()->postJson('/api/timeline/post/comment/create', [
            'post_id' => $post->id, 'parent_id' => $root['id'], 'content' => 'First reply',
        ])->assertOk()->json('data.comment');
        $nested = $this->actingAs($viewer)->withoutMiddleware()->postJson('/api/timeline/post/comment/create', [
            'post_id' => $post->id, 'parent_id' => $reply['id'], 'content' => 'Reply to the reply',
        ])->assertOk()->json('data.comment');

        $this->assertSame($root['id'], $reply['thread_root_id']);
        $this->assertSame($root['id'], $nested['thread_root_id']);
        $this->assertSame($reply['id'], $nested['parent_id']);

        $roots = $this->actingAs($viewer)->withoutMiddleware()->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads?sort=newest')->assertOk();
        $this->assertCount(1, $roots->json('data'));
        $this->assertSame(2, $roots->json('data.0.reply_count'));
        $this->assertSame(3, $roots->json('meta.comments_count'));

        $replies = $this->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads/'.$root['id'].'/replies')->assertOk();
        $this->assertSame([$reply['id'], $nested['id']], array_column($replies->json('data'), 'id'));
        $this->assertSame($reply['id'], $replies->json('data.1.parent_id'));
        $this->assertSame($root['id'], $replies->json('meta.thread_root_id'));
        $this->assertIsInt($post->fresh()->comments_count);
    }

    public function test_reply_to_comment_from_another_post_is_rejected(): void
    {
        Notification::fake();
        $author = $this->user('cross-author');
        $viewer = $this->user('cross-viewer');
        $firstPost = $this->createPostFixture($author);
        $secondPost = $this->createPostFixture($author);
        $parent = Comment::create([
            'post_id' => $firstPost->id, 'user_id' => $viewer->id, 'parent_id' => null,
            'thread_root_id' => null, 'content' => 'Other post root', 'text_language' => 'en',
        ]);
        $parent->update(['thread_root_id' => $parent->id]);

        $this->actingAs($viewer)->withoutMiddleware()->postJson('/api/timeline/post/comment/create', [
            'post_id' => $secondPost->id, 'parent_id' => $parent->id, 'content' => 'Invalid reply',
        ])->assertNotFound();

        $this->assertSame(1, Comment::where('post_id', $firstPost->id)->count());
        $this->assertSame(0, Comment::where('post_id', $secondPost->id)->count());
    }

    public function test_replies_use_id_cursor_without_duplicates_and_sort_is_deterministic(): void
    {
        Notification::fake();
        $author = $this->user('sort-author');
        $viewer = $this->user('sort-viewer');
        $post = $this->createPostFixture($author);
        $older = Comment::create(['post_id' => $post->id, 'user_id' => $viewer->id, 'content' => 'Older root', 'text_language' => 'en', 'created_at' => now()->subDay()]);
        $newer = Comment::create(['post_id' => $post->id, 'user_id' => $viewer->id, 'content' => 'Newer root', 'text_language' => 'en', 'created_at' => now()]);
        $older->update(['thread_root_id' => $older->id]);
        $newer->update(['thread_root_id' => $newer->id]);
        Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $older->id, 'thread_root_id' => $older->id, 'content' => 'Author reply', 'text_language' => 'en']);
        for ($index = 0; $index < 43; $index++) {
            Comment::create(['post_id' => $post->id, 'user_id' => $viewer->id, 'parent_id' => $newer->id, 'thread_root_id' => $newer->id, 'content' => 'Reply '.$index, 'text_language' => 'en']);
        }

        $this->actingAs($viewer)->withoutMiddleware()
            ->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads?sort=relevant')
            ->assertOk()->assertJsonPath('data.0.id', $older->id);
        $newest = $this->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads?sort=newest')->assertOk();
        $this->assertSame([$newer->id, $older->id], array_column($newest->json('data'), 'id'));

        $pageOne = $this->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads/'.$newer->id.'/replies')->assertOk();
        $this->assertCount(20, $pageOne->json('data'));
        $pageTwo = $this->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads/'.$newer->id.'/replies?cursor='.$pageOne->json('meta.next_cursor'))->assertOk();
        $pageThree = $this->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads/'.$newer->id.'/replies?cursor='.$pageTwo->json('meta.next_cursor'))->assertOk();
        $all = array_merge(array_column($pageOne->json('data'), 'id'), array_column($pageTwo->json('data'), 'id'), array_column($pageThree->json('data'), 'id'));
        $expected = Comment::where('thread_root_id', $newer->id)->where('id', '<>', $newer->id)->orderBy('created_at')->orderBy('id')->pluck('id')->all();
        $this->assertSame($expected, $all);
        $this->assertCount(count($all), array_unique($all));
    }

    public function test_a_thousand_replies_keep_the_root_page_short_until_the_thread_is_opened(): void
    {
        Notification::fake();
        $author = $this->user('large-thread-author');
        $viewer = $this->user('large-thread-viewer');
        $post = $this->createPostFixture($author);
        $root = Comment::create([
            'post_id' => $post->id, 'user_id' => $viewer->id, 'content' => 'Root body', 'text_language' => 'en',
        ]);
        $root->update(['thread_root_id' => $root->id]);

        foreach (array_chunk(range(1, 1000), 250) as $batch) {
            $now = now();
            Comment::insert(array_map(fn ($index) => [
                'post_id' => $post->id, 'user_id' => $viewer->id, 'parent_id' => $root->id,
                'thread_root_id' => $root->id, 'content' => 'Reply body '.$index, 'text_language' => 'en',
                'created_at' => $now->copy()->addSeconds($index), 'updated_at' => $now->copy()->addSeconds($index),
            ], $batch));
        }

        $roots = $this->actingAs($viewer)->withoutMiddleware()
            ->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reply_count', 1000);
        $this->assertSame('Root body', $roots->json('data.0.content'));
        $this->assertSame(1001, $roots->json('meta.comments_count'));
        $this->assertStringNotContainsString('Reply body', $roots->getContent());

        $firstPage = $this->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads/'.$root->id.'/replies')
            ->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.has_more', true);
        $this->assertStringContainsString('Reply body', $firstPage->getContent());
    }

    public function test_most_relevant_uses_total_reactors_not_the_number_of_emoji_types(): void
    {
        Notification::fake();
        $author = $this->user('reaction-sort-author');
        $viewer = $this->user('reaction-sort-viewer');
        $post = $this->createPostFixture($author);
        $time = now()->startOfSecond();
        $manyTypes = Comment::create([
            'post_id' => $post->id, 'user_id' => $viewer->id, 'content' => 'Many total reactors',
            'text_language' => 'en', 'created_at' => $time, 'updated_at' => $time,
        ]);
        $oneType = Comment::create([
            'post_id' => $post->id, 'user_id' => $viewer->id, 'content' => 'One reaction type',
            'text_language' => 'en', 'created_at' => $time, 'updated_at' => $time,
        ]);
        foreach (['heart', 'thumbsup'] as $type) {
            $manyTypes->reactions()->create(['unified_id' => $type, 'users' => array_fill(0, 30, $viewer->id), 'reactions_count' => 30]);
        }
        $oneType->reactions()->create([
            'unified_id' => 'fire', 'users' => array_fill(0, 50, $viewer->id), 'reactions_count' => 50,
        ]);

        $response = $this->actingAs($viewer)->withoutMiddleware()
            ->getJson('/api/timeline/post/'.$post->hash_id.'/comment-threads?sort=relevant')->assertOk();

        $this->assertSame([$manyTypes->id, $oneType->id], array_column($response->json('data'), 'id'));
    }

    public function test_comment_edit_is_allowed_for_fifteen_minutes_and_report_reasons_exist(): void
    {
        Notification::fake();
        $author = $this->user('edit-author');
        $viewer = $this->user('edit-viewer');
        $post = $this->createPostFixture($author);
        $comment = Comment::create([
            'post_id' => $post->id, 'user_id' => $viewer->id, 'content' => 'Before edit', 'text_language' => 'en',
        ]);
        $comment->update(['thread_root_id' => $comment->id]);

        $this->actingAs($viewer)->withoutMiddleware()->putJson('/api/timeline/post/comment/update', [
            'id' => $comment->id, 'content' => 'After edit',
        ])->assertOk()->assertJsonPath('data.comment.content', 'After edit')->assertJsonPath('data.comment.is_edited', true);

        $comment->forceFill(['created_at' => now()->subMinutes(16)])->save();
        $this->putJson('/api/timeline/post/comment/update', [
            'id' => $comment->id, 'content' => 'Too late',
        ])->assertUnauthorized();

        $this->getJson('/api/feedback/report/reasons?type=comment')->assertOk()->assertJsonStructure(['data' => ['title', 'reasons']]);
        $reported = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'content' => 'Reported comment', 'text_language' => 'en']);
        $reported->update(['thread_root_id' => $reported->id]);
        $this->postJson('/api/feedback/report/send', ['type' => 'comment', 'reason_index' => 0, 'reportable_id' => $reported->id])
            ->assertOk();
        $this->assertDatabaseHas('reports', ['reportable_type' => Comment::class, 'reportable_id' => $reported->id, 'reporter_id' => $viewer->id]);
    }

    public function test_deleting_a_root_cleans_every_descendant_reaction_report_and_photo_and_returns_integer_count(): void
    {
        Notification::fake();
        $author = $this->user('delete-author');
        $post = $this->createPostFixture($author);
        $root = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'content' => 'Root', 'text_language' => 'en']);
        $root->update(['thread_root_id' => $root->id]);
        $reply = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $root->id, 'thread_root_id' => $root->id, 'content' => 'Reply', 'text_language' => 'en']);
        $nested = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $reply->id, 'thread_root_id' => $root->id, 'content' => 'Nested', 'text_language' => 'en']);
        foreach ([$root, $reply, $nested] as $comment) {
            $comment->reactions()->create(['reactions_count' => 1, 'unified_id' => 'heart', 'users' => [$author->id]]);
            $comment->reports()->create(['reporter_id' => $author->id, 'reason_index' => 0, 'type' => 'comment']);
        }
        $reply->media()->create([
            'source_path' => 'comments/photo.webp', 'disk' => 'public', 'extension' => 'webp',
            'mime' => 'image/webp', 'size' => 100, 'type' => 'image', 'status' => 'processed',
        ]);
        $post->update(['comments_count' => 3]);

        $response = $this->actingAs($author)->withoutMiddleware()->deleteJson('/api/timeline/post/comment/delete', ['id' => $root->id])->assertOk();

        $this->assertIsInt($response->json('data.post.comments_count.raw'));
        $this->assertSame(0, $response->json('data.post.comments_count.raw'));
        $this->assertSame(0, Comment::where('post_id', $post->id)->count());
        $this->assertSame(0, Media::where('mediaable_type', Comment::class)->count());
        $this->assertDatabaseMissing('reactions', ['reactable_type' => Comment::class, 'reactable_id' => $root->id]);
        $this->assertDatabaseMissing('reports', ['reportable_type' => Comment::class, 'reportable_id' => $nested->id]);
    }

    public function test_comment_media_publication_accepts_photo_only_and_rejects_gif_images(): void
    {
        $author = $this->user('media-author');
        $post = $this->createPostFixture($author);
        $publication = MediaPublication::create([
            'id' => (string) Str::uuid(), 'user_id' => $author->id, 'client_uid' => (string) Str::uuid(),
            'request_hash' => str_repeat('a', 64), 'kind' => 'comment', 'status' => 'uploading',
            'has_video' => false, 'payload' => ['content' => '', 'post_id' => $post->id], 'profile' => [],
            'expires_at' => now()->addDay(),
        ]);
        MediaPublicationItem::create([
            'id' => (string) Str::uuid(), 'publication_id' => $publication->id, 'client_uid' => (string) Str::uuid(),
            'position' => 0, 'type' => 'image', 'name' => 'photo.jpg', 'mime' => 'image/jpeg',
            'size' => 100, 'metadata' => [],
        ]);
        $this->mock(PublicationService::class)->shouldReceive('create')->once()->andReturn($publication->load('items'));

        $this->actingAs($author)->withoutMiddleware()->postJson('/api/media-publications/', [
            'client_uid' => (string) Str::uuid(), 'kind' => 'comment', 'post_id' => $post->id,
            'comment_mode' => 'create', 'content' => '',
            'items' => [[
                'client_uid' => (string) Str::uuid(), 'type' => 'image', 'name' => 'photo.jpg',
                'mime' => 'image/jpeg', 'size' => 100,
            ]],
        ])->assertCreated()->assertJsonPath('data.kind', 'comment');

        $this->postJson('/api/media-publications/', [
            'client_uid' => (string) Str::uuid(), 'kind' => 'comment', 'post_id' => $post->id,
            'comment_mode' => 'create', 'content' => '',
            'items' => [[
                'client_uid' => (string) Str::uuid(), 'type' => 'image', 'name' => 'photo.gif',
                'mime' => 'image/gif', 'size' => 100,
            ]],
        ])->assertUnprocessable();
    }

    public function test_thread_root_backfill_finds_deep_descendants_and_can_be_rerun(): void
    {
        $author = $this->user('backfill-author');
        $post = $this->createPostFixture($author);
        $root = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'content' => 'Root', 'text_language' => 'en']);
        $reply = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $root->id, 'content' => 'Reply', 'text_language' => 'en']);
        $nested = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $reply->id, 'content' => 'Nested', 'text_language' => 'en']);
        $otherPost = $this->createPostFixture($author);
        $foreignParent = Comment::create(['post_id' => $otherPost->id, 'user_id' => $author->id, 'content' => 'Foreign root', 'text_language' => 'en']);
        $foreignChild = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $foreignParent->id, 'content' => 'Cross-post orphan', 'text_language' => 'en']);
        $cycleA = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'content' => 'Cycle A', 'text_language' => 'en']);
        $cycleB = Comment::create(['post_id' => $post->id, 'user_id' => $author->id, 'content' => 'Cycle B', 'text_language' => 'en']);
        $cycleA->update(['parent_id' => $cycleB->id]);
        $cycleB->update(['parent_id' => $cycleA->id]);
        foreach (array_chunk(range(1, 1000), 250) as $batch) {
            $now = now();
            Comment::insert(array_map(fn ($index) => [
                'post_id' => $post->id, 'user_id' => $author->id, 'parent_id' => $root->id,
                'thread_root_id' => null, 'content' => 'Legacy reply '.$index, 'text_language' => 'en',
                'created_at' => $now->copy()->addSeconds($index), 'updated_at' => $now->copy()->addSeconds($index),
            ], $batch));
        }

        $this->artisan('comments:backfill-thread-roots --chunk=100')->assertSuccessful();
        $this->assertSame([$root->id, $root->id, $root->id], Comment::whereIn('id', [$root->id, $reply->id, $nested->id])->orderBy('id')->pluck('thread_root_id')->all());
        $this->assertDatabaseHas('comments', ['id' => $foreignChild->id, 'parent_id' => null, 'thread_root_id' => $foreignChild->id]);
        $this->assertDatabaseHas('comments', ['id' => $cycleB->id, 'parent_id' => null, 'thread_root_id' => $cycleB->id]);
        $this->assertSame($cycleB->id, (int) $cycleA->fresh()->thread_root_id);
        $this->assertSame(1003, Comment::where('thread_root_id', $root->id)->count());
        $this->artisan('comments:backfill-thread-roots --chunk=100')->assertSuccessful();
        $this->assertSame(1003, Comment::where('thread_root_id', $root->id)->count());
    }

    private function user(string $prefix): User
    {
        $username = $prefix.'-'.Str::lower(Str::random(6));
        $user = User::create([
            'first_name' => 'Test', 'last_name' => 'User', 'username' => $username, 'caption' => '@'.$username,
            'email' => $username.'@example.com', 'phone' => '', 'website' => '', 'bio' => '', 'country' => null,
            'city' => null, 'birth_day' => null, 'birth_month' => null, 'birth_year' => null, 'age' => null,
            'gender' => 'male', 'last_active' => now()->timestamp, 'language' => 'en', 'avatar' => null,
            'cover' => null, 'verified' => false, 'tips' => [], 'email_verified_at' => now(),
            'password' => Hash::make('password'), 'role' => UserRole::USER, 'theme' => 'light',
            'publications_count' => 0, 'followers_count' => 0, 'following_count' => 0,
            'status' => UserStatus::ACTIVE, 'type' => UserType::AUTHOR,
        ]);
        foreach ([NotificationType::EMAIL, NotificationType::PUSH] as $type) {
            UserNotificationSettings::create(['user_id' => $user->id, 'type' => $type]);
        }
        return $user;
    }

    private function createPostFixture(User $author): Post
    {
        return Post::create([
            'user_id' => $author->id, 'title' => '', 'content' => 'Post', 'status' => PostStatus::ACTIVE,
            'type' => PostType::TEXT, 'text_language' => 'en', 'comments_count' => 0,
        ]);
    }
}
