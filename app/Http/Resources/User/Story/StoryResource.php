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

namespace App\Http\Resources\User\Story;

use App\Support\Story\StoryFrameProgress;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = me()->isRoot() || (me()->id === $this->user_id);

        return [
            'id' => $this->id,
            'story_uuid' => $this->story_uuid,
            // TODO: This is a temporary solution to get the story URL.
            // We need to implement a proper solution in future updates.
            
            'url' => url("stories/{$this->story_uuid}"),
            'relations' => [
                'user' => UserPreviewResource::make($this->user),
                'frames' => $this->frames->map(function($frameItem) {
                    $reactionActivity = $this->getStoryFrameReactionActivity($frameItem);

                    return [
                        'id' => $frameItem->id,
                        'type' => $frameItem->type->value,
                        'status' => $frameItem->status->value,
                        'content' => $frameItem->content,
                        'media' => $this->getStoryMedia($frameItem),
                        'meta' => $frameItem->meta,
                        'progress' => StoryFrameProgress::make($frameItem),
                        'duration_seconds' => $frameItem->duration_seconds,
                        'views_count' => [
                            'raw' => $frameItem->views_count,
                            'formatted' => $frameItem->views_count,
                        ],
                        'likes_count' => $this->getStoryFrameLikesCount($frameItem),
                        'reactions_count' => $this->getStoryFrameReactionsCount($frameItem),
                        'reactions_summary' => $this->getStoryFrameReactionsSummary($frameItem),
                        'relations' => [
                            'views' => []
                        ],
                        'date' => [
                            'time_ago' => $this->getPublishedAt($frameItem)->shortAbsoluteDiffForHumans()
                        ],
                        'activity' => [
                            'is_seen' => $this->checkIfStoryFrameSeen($frameItem),
                            ...$reactionActivity,
                        ]
                    ];
                })
            ],
            'permissions' => [
                'can_delete' => $isOwner,
                'can_hide' => empty($isOwner),
                'can_report' => empty($isOwner),
            ],
            'meta' => [
                'is_owner' => $isOwner
            ]
        ];
    }

    private function checkIfStoryFrameSeen($frameItem)
    {
        if($frameItem->relationLoaded('views')) {
            return $frameItem->views->contains('user_id', me()->id);
        }

        return $frameItem->views()->where('user_id', me()->id)->exists();
    }

    private function getStoryFrameReactionActivity($frameItem): array
    {
        $reactionUnifiedId = $this->getStoryFrameReactionForUser($frameItem)?->unified_id;

        return [
            'has_liked' => $reactionUnifiedId === $frameItem::PRIVATE_LIKE_UNIFIED_ID,
            'has_reacted' => ! empty($reactionUnifiedId),
            'reaction_unified_id' => $reactionUnifiedId,
            'reaction_image_url' => $reactionUnifiedId ? reaction_image_url($reactionUnifiedId) : null,
        ];
    }

    private function getStoryFrameLikesCount($frameItem): array
    {
        $likesCount = $frameItem->relationLoaded('reactions')
            ? ($frameItem->reactions->firstWhere('unified_id', $frameItem::PRIVATE_LIKE_UNIFIED_ID)?->reactions_count ?? 0)
            : ($frameItem->reactions()->where('unified_id', $frameItem::PRIVATE_LIKE_UNIFIED_ID)->value('reactions_count') ?? 0);

        return [
            'raw' => $likesCount,
            'formatted' => $likesCount,
        ];
    }

    private function getStoryFrameReactionsCount($frameItem): array
    {
        $reactionsCount = $frameItem->relationLoaded('reactions')
            ? (int) $frameItem->reactions->sum('reactions_count')
            : (int) $frameItem->reactions()->sum('reactions_count');

        return [
            'raw' => $reactionsCount,
            'formatted' => $reactionsCount,
        ];
    }

    private function getStoryFrameReactionsSummary($frameItem): array
    {
        $reactions = $frameItem->relationLoaded('reactions')
            ? $frameItem->reactions
            : $frameItem->reactions()->get();

        return $reactions->sortByDesc('reactions_count')->values()->map(function($reactionItem) {
            return [
                'unified_id' => $reactionItem->unified_id,
                'image_url' => reaction_image_url($reactionItem->unified_id),
                'total' => (int) $reactionItem->reactions_count,
                'has_reacted' => collect($reactionItem->users ?? [])->contains(me()->id),
            ];
        })->toArray();
    }

    private function getStoryFrameReactionForUser($frameItem)
    {
        if($frameItem->relationLoaded('reactions')) {
            return $frameItem->reactions->first(function($reactionItem) {
                return collect($reactionItem->users ?? [])->contains(me()->id);
            });
        }

        return $frameItem->reactions()->whereJsonContains('users', me()->id)->first();
    }

    private function getStoryMedia($frameItem)
    {
        return MediaResource::make($frameItem->media->first());
    }

    private function getPublishedAt($frameItem): Carbon
    {
        if(! empty($frameItem->expires_at)) {
            return Carbon::parse($frameItem->expires_at->getTimestamp())
                ->subHours(max(1, (int) config('story.expire_after_hours', 24)))
                ->locale(app()->getLocale());
        }

        return Carbon::parse($frameItem->created_at->getTimestamp())->locale(app()->getLocale());
    }
}
