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
use App\Rules\X\XRule;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Traits\Http\Controllers\Api\User\Story\InteractsWithDraftStoryFrame;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
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
        $storiesFeed = $this->visibleStoriesQuery()->with([
            'user:id,avatar,first_name,last_name',
            'frames.views',
            'frames.media'
        ])->latest('updated_at')->get();

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

            $updateData = [
                'content' => e($request->string('content')),
                'status' => $isVideo ? StoryStatus::PROCESSING : StoryStatus::ACTIVE,
                'expires_at' => now()->addHours(24)
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

            $myStory = $this->draftStoryFrame->story()->with([
                'user:id,avatar,first_name,last_name',
                'frames.views',
                'frames.media'
            ])->first();

            return $this->responseSuccess([
                'data' => FeedResource::make($myStory)
            ]);
        }
    }

    public function recordView(Request $request)
    {
        $storyFrameId = $request->integer('frame_id');

        if(is_positive($storyFrameId)) {
            $frameData = StoryFrame::active()->where('id', $storyFrameId)->first();

            if($frameData) {
                $isSeen = $frameData->views()->where('user_id', me()->id)->exists();

                if(empty($isSeen)) {
                    $frameData->views()->create([
                        'user_id' => me()->id,
                        'viewed_at' => now()
                    ]);

                    $frameData->increment('views_count');
                }

                return $this->responseSuccess([
                   'data' => null
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

                $updatedStory = $this->visibleStoriesQuery()->where('id', $storyId)->with([
                    'user:id,avatar,first_name,last_name',
                    'frames.views',
                    'frames.media'
                ])->first();

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
            $frameData = StoryFrame::active()->where('id', $frameId)->first();

            if($frameData) {
                $frameViews = $frameData->views()->withUser()->get();

                return $this->responseSuccess([
                   'data' => ViewCollection::make($frameViews)
                ]);
            }
        }

        return $this->responseResourceNotFoundError('StoryFrame', $frameId);
    }

    private function visibleStoriesQuery()
    {
        return Story::query()->where(function($query) {
            $query->whereHas('frames', function($hasQuery) {
                $hasQuery->where('expires_at', '>', now())->where('status', StoryStatus::ACTIVE);
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
                $withQuery->where('expires_at', '>', now())->where(function($frameQuery) {
                    $frameQuery->where('status', StoryStatus::ACTIVE)->orWhere(function($processingQuery) {
                        $processingQuery->where('status', StoryStatus::PROCESSING)->whereHas('story', function($storyQuery) {
                            $storyQuery->where('user_id', me()->id);
                        })->whereHas('media', function($mediaQuery) {
                            $this->applyProcessableStoryMediaQuery($mediaQuery);
                        });
                    });
                });
            },
            'frames.views',
            'frames.media'
        ];
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
