<?php

namespace App\Services\TestContent;

use App\Enums\Post\PostStatus;
use App\Enums\Post\PostType;
use App\Models\Post;
use App\Models\TestContentPublication;
use App\Models\User;
use App\Services\Timeline\TopicExtractionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestAccountContentPublisher
{
    public function __construct(
        private readonly OriginalTestArticleFactory $articleFactory,
        private readonly TopicExtractionService $topicExtractionService,
    ) {
    }

    public function eligibleUsers(): Builder
    {
        return User::query()
            ->active()
            ->whereRaw('LOWER(email) LIKE ?', ['%.test']);
    }

    public function eligibleCount(): int
    {
        return $this->eligibleUsers()->count();
    }

    public function alreadyPublishedCount(string $campaignKey): int
    {
        return TestContentPublication::query()
            ->where('campaign_key', $campaignKey)
            ->where('status', 'published')
            ->whereNotNull('post_id')
            ->count();
    }

    public function publish(string $campaignKey, int $limit = 0, ?callable $onProgress = null): array
    {
        $summary = [
            'published' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $userIds = $this->eligibleUsers()
            ->orderBy('id')
            ->pluck('id');

        if($limit > 0) {
            $userIds = $userIds->take($limit);
        }

        foreach($userIds->chunk(100) as $chunk) {
            User::query()
                ->whereIn('id', $chunk)
                ->orderBy('id')
                ->get()
                ->each(function(User $user) use ($campaignKey, &$summary, $onProgress) {
                    try {
                        $published = $this->publishForUser($user, $campaignKey);
                        $summary[$published ? 'published' : 'skipped']++;
                    }
                    catch(Throwable $exception) {
                        report($exception);
                        $this->markFailed($user, $campaignKey, $exception);
                        $summary['failed']++;
                    }

                    if($onProgress) {
                        $onProgress();
                    }
                });
        }

        return $summary;
    }

    private function publishForUser(User $user, string $campaignKey): bool
    {
        return DB::transaction(function() use ($user, $campaignKey) {
            $publication = TestContentPublication::query()
                ->lockForUpdate()
                ->firstOrCreate([
                    'campaign_key' => $campaignKey,
                    'user_id' => $user->id,
                ], [
                    'content_key' => '',
                    'status' => 'reserved',
                ]);

            if($publication->status === 'published' && $publication->post_id) {
                return false;
            }

            $article = $this->articleFactory->make($user, $campaignKey);

            $post = Post::query()->create([
                'user_id' => $user->id,
                'title' => $article['title'],
                'content' => $article['content'],
                'status' => PostStatus::ACTIVE,
                'type' => PostType::TEXT,
                'text_language' => '',
                'is_ai_generated' => true,
            ]);

            $post->text_language = $post->getContentLanguage();
            $post->save();

            $this->topicExtractionService->syncPostTopics($post);

            User::query()->whereKey($user->id)->increment('publications_count');

            $publication->update([
                'post_id' => $post->id,
                'content_key' => $article['content_key'],
                'status' => 'published',
                'error_message' => null,
                'published_at' => now(),
            ]);

            return true;
        }, 3);
    }

    private function markFailed(User $user, string $campaignKey, Throwable $exception): void
    {
        TestContentPublication::query()->updateOrCreate([
            'campaign_key' => $campaignKey,
            'user_id' => $user->id,
        ], [
            'status' => 'failed',
            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
        ]);
    }
}
