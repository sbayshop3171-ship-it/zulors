<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Story;

use App\Actions\Story\DeleteStoryFrameAction;
use App\Database\Configs\Table;
use App\Enums\Media\MediaStatus;
use App\Enums\Story\StoryStatus;
use App\Events\User\Story\StoryCreatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Story\FeedCollection;
use App\Http\Resources\User\Story\FeedResource;
use App\Http\Resources\User\Story\StoryCollection;
use App\Http\Resources\User\Story\ViewCollection;
use App\Models\Story;
use App\Models\StoryFrame;
use App\Notifications\User\Story\StoryLikedNotification;
use App\Rules\X\XRule;
use App\Services\Reaction\ReactionService;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Traits\Http\Controllers\Api\User\Story\InteractsWithDraftStoryFrame;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class StoryController extends Controller
{
    use InteractsWithDraftStoryFrame,
        AuthorizesRequests,
        SupportsApiResponses;

    public function __construct()
    {
        $this->fetchOrInitializeDraftStoryFrame();
    }

    public function getStories(Request $request, string $storyId)
    {
        if(Str::isUuid($storyId)) {
            $storyData = $this->visibleStoriesQuery()->where('story_uuid', $storyId)->with($this->storyViewerRelations())->first();

            if($storyData) {
                $otherRelevantStories = $this->visibleStoriesQuery()->whereNot('story_uuid', $storyId)->with($this->storyViewerRelations())->get();
                $stories = $otherRelevantStories->prepend($storyData);

                return $this->responseSuccess([
                    'data' => StoryCollection::make($stories)
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Story', $storyId);
    }

    public function getFeed(Request $request)
    {
        $storiesFeed = $this->visibleStoriesQuery()->with($this->storyFeedRelations())->latest('updated_at')->get();

        return $this->responseSuccess([
            'data' => FeedCollection::make($storiesFeed)
        ]);
    }

    public function create(Request $request)
    {
        $storyMedia = $this->draftStoryFrame->media->first();

        if(empty($storyMedia)) {
            return $this->responseError([
                'message' => 'Media file is required before creating a story.',
                'errors' => [
                    'media' => [
                        'Media file is required before creating a story.'
                    ]
                ]
            ]);
        }

        else{
            $request->validate([
                'content' => ['nullable', 'string', XRule::join('max', config('story.validation.content.max'))]
            ]);

            $isVideo = $this->draftStoryFrame->type->isVideo();
            $publishedAt = now();
            $expiresAt = $publishedAt->copy()->addHours(max(1, (int) config('story.expire_after_hours', 24)));

            $updateData = [
                'content' => e($request->string('content')),
                'status' => $isVideo ? StoryStatus::PROCESSING : StoryStatus::ACTIVE,
                'created_at' => $publishedAt,
                'expires_at' => $expiresAt
            ];

            if(! $isVideo) {
                $updateData['duration_seconds'] = config('story.image_clip_size');
            }

            $this->draftStoryFrame->update($updateData);

            if($isVideo) {
                $storyMedia->status = MediaStatus::PROCESSING;
                $storyMedia->metadata = array_merge($storyMedia->metadata ?? [], [
                    'upload_progress' => 100,
                    'upload_state' => 'uploaded',
                    'upload_completed_at' => now()->toIso8601String(),
                    'processing_progress' => 1,
                    'processing_state' => 'queued',
                    'processing_dispatched_at' => now()->toIso8601String(),
                    'processing_updated_at' => now()->toIso8601String()
                ]);
                $storyMedia->save();
            }

            event(new StoryCreatedEvent($this->draftStoryFrame));

            $myStory = $this->draftStoryFrame->story()->with($this->storyFeedRelations())->first();

            return $this->responseSuccess([
                'data' => FeedResource::make($myStory)
            ]);
        }
    }

    public function recordView(Request $request)
    {
        $storyFrameId = $request->integer('frame_id');

        if(is_positive($storyFrameId)) {
            $frameData = $this->findVisibleStoryFrame($storyFrameId);

            if($frameData) {
                $this->ensureStoryFrameViewed($frameData);

                return $this->responseSuccess([
                   'data' => null
                ]);
            }
        }

        return $this->responseResourceNotFoundError('StoryFrame', $storyFrameId);
    }

    public function toggleLike(Request $request, ReactionService $reactionService)
    {
        $storyFrameId = $request->integer('frame_id');

        if(is_positive($storyFrameId)) {
            $frameData = $this->findVisibleStoryFrame($storyFrameId, [
                'story.user.pushNotificationSettings',
                'media',
                'reactions',
            ]);

            if($frameData) {
                if($frameData->story->user_id === me()->id) {
                    return $this->responseError([
                        'message' => __('api/story.cannot_like_own_story'),
                        'errors' => [
                            'frame_id' => [
                                __('api/story.cannot_like_own_story'),
                            ],
                        ],
                    ], 403);
                }

                $this->ensureStoryFrameViewed($frameData);

                $hasLiked = $reactionService
                    ->setReactable($frameData)
                    ->setUserId(me()->id)
                    ->setUnifiable(StoryFrame::PRIVATE_LIKE_UNIFIED_ID)
                    ->handleReaction();

                $frameData->load('reactions');

                if($hasLiked) {
                    $frameData->story->user?->notify(new StoryLikedNotification($frameData));
                }

                return $this->responseSuccess([
                    'data' => [
                        'frame_id' => $frameData->id,
                        'likes_count' => $this->getStoryFrameLikesCount($frameData),
                        'activity' => [
                            'has_liked' => $hasLiked,
                            'is_seen' => true,
                        ],
                    ],
                ]);
            }
        }

        return $this->responseResourceNotFoundError('StoryFrame', $storyFrameId);
    }

    public function deleteStory(Request $request)
    {
        $storyFrameId = $request->integer('frame_id');

        if(is_positive($storyFrameId)) {
            $frameData = StoryFrame::where('id', $storyFrameId)->with('story')->first();

            try {
                if(empty($frameData)) {
                    return $this->responseResourceNotFoundError('StoryFrame', $storyFrameId);
                }

                $this->authorize('delete', $frameData);

                $storyId = $frameData->story_id;

                (new DeleteStoryFrameAction($frameData))->execute();

                $updatedStory = $this->visibleStoriesQuery()->where('id', $storyId)->with($this->storyFeedRelations())->first();

                return $this->responseSuccess([
                    'data' => $updatedStory ? FeedResource::make($updatedStory) : null
                ]);
            } catch (Throwable $th) {
                return $this->responseResourceNotFoundError('StoryFrame', $storyFrameId);
            }
        }

        return $this->responseResourceNotFoundError('StoryFrame', $storyFrameId);
    }

    public function getStoryViews(Request $request, $frameId)
    {
        if(is_positive($frameId)) {
            $frameData = $this->findVisibleStoryFrame($frameId, [
                'story',
                'reactions',
            ]);

            if($frameData) {
                if(! me()->isRoot() && ($frameData->story?->user_id !== me()->id)) {
                    return $this->responseError([
                        'message' => __('errors.unauthorized'),
                    ], 403);
                }

                $likedUserIds = $this->getStoryFrameLikedUserIds($frameData);
                $frameViews = $frameData->views()->withUser()->get()->map(function($viewItem) use ($likedUserIds) {
                    $viewItem->setAttribute('has_liked_story', $likedUserIds->contains($viewItem->user_id));

                    return $viewItem;
                })->sortByDesc(function($viewItem) {
                    return (int) $viewItem->getAttribute('has_liked_story');
                })->values();

                return $this->responseSuccess([
                   'data' => ViewCollection::make($frameViews),
                   'meta' => [
                        'likes_count' => $this->getStoryFrameLikesCount($frameData),
                        'views_count' => [
                            'raw' => $frameData->views_count,
                            'formatted' => $frameData->views_count,
                        ],
                   ],
                ]);
            }
        }

        return $this->responseResourceNotFoundError('StoryFrame', $frameId);
    }

    private function visibleStoriesQuery()
    {
        return Story::query()->where(function($query) {
            $query->whereHas('frames', function($hasQuery) {
                $hasQuery->relevantStories();
            })->orWhere(function($ownerQuery) {
                $ownerQuery->where('user_id', me()->id)->whereHas('frames', function($hasQuery) {
                    $this->applyVisibleProcessingFrameQuery($hasQuery);
                });
            });
        })->whereNotIn('user_id', function($query) {
            $query->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
        })->whereNotIn('user_id', function($query) {
            $query->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
        });
    }

    private function storyViewerRelations(): array
    {
        return [
            'user:id,avatar,first_name,last_name,username,verified',
            'frames' => function($withQuery) {
                $this->applyVisibleStoryFrameQuery($withQuery);
            },
            'frames.views',
            'frames.reactions',
            'frames.media'
        ];
    }

    private function storyFeedRelations(): array
    {
        return [
            'user:id,avatar,first_name,last_name',
            'frames' => function($withQuery) {
                $this->applyVisibleStoryFrameQuery($withQuery);
            },
            'frames.views',
            'frames.media'
        ];
    }

    private function findVisibleStoryFrame(int $frameId, array $relations = [])
    {
        return StoryFrame::query()->relevantStories()->where('id', $frameId)->whereHas('story', function($storyQuery) {
            $storyQuery->whereIn('id', $this->visibleStoriesQuery()->select('id'));
        })->with($relations)->first();
    }

    private function ensureStoryFrameViewed(StoryFrame $frameData): bool
    {
        $isSeen = $frameData->views()->where('user_id', me()->id)->exists();

        if($isSeen) {
            return false;
        }

        $frameData->views()->create([
            'user_id' => me()->id,
            'viewed_at' => now(),
        ]);

        $frameData->increment('views_count');
        $frameData->views_count += 1;

        return true;
    }

    private function getStoryFrameLikesCount(StoryFrame $frameData): array
    {
        $likesCount = $frameData->reactions->firstWhere('unified_id', StoryFrame::PRIVATE_LIKE_UNIFIED_ID)?->reactions_count ?? 0;

        return [
            'raw' => $likesCount,
            'formatted' => $likesCount,
        ];
    }

    private function getStoryFrameLikedUserIds(StoryFrame $frameData): Collection
    {
        return collect($frameData->reactions->firstWhere('unified_id', StoryFrame::PRIVATE_LIKE_UNIFIED_ID)?->users ?? []);
    }

    private function applyVisibleStoryFrameQuery($query): void
    {
        $query->whereNotNull('expires_at')->where('expires_at', '>', now())->where(function($frameQuery) {
            $frameQuery->where('status', StoryStatus::ACTIVE)->orWhere(function($processingQuery) {
                $processingQuery->where('status', StoryStatus::PROCESSING)->whereHas('story', function($storyQuery) {
                    $storyQuery->where('user_id', me()->id);
                })->whereHas('media', function($mediaQuery) {
                    $this->applyProcessableStoryMediaQuery($mediaQuery);
                });
            });
        });
    }

    private function applyVisibleProcessingFrameQuery($query): void
    {
        $query->where('expires_at', '>', now())
            ->where('status', StoryStatus::PROCESSING)
            ->whereHas('media', function($mediaQuery) {
                $this->applyProcessableStoryMediaQuery($mediaQuery);
            });
    }

    private function applyProcessableStoryMediaQuery($query): void
    {
        $query->where('status', '!=', MediaStatus::FAILED)
            ->where(function($metadataQuery) {
                $metadataQuery->whereNull('metadata->processing_state')
                    ->orWhere('metadata->processing_state', '!=', 'failed');
            });
    }
}
