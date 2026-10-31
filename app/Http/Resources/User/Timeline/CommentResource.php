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

namespace App\Http\Resources\User\Timeline;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\Media\MediaResource;

class CommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'thread_root_id' => $this->thread_root_id,
            'reply_count' => $this->parent_id ? 0 : max(0, (int) ($this->reply_count ?? $this->threadReplies()->count()) - 1),
            'has_parent' => (empty($this->parent_id)) ? false : true,
            'content' => e($this->content),
            'media' => $this->whenLoaded('media', fn () => MediaResource::collection($this->media)->resolve($request)),
            'is_edited' => $this->edited_at !== null,
            'relations' => [
                'user' => [
                    'avatar_url' => $this->user->avatar_url,
                    'name' => $this->user->name,
                    'username' => $this->user->username,
                    'id' => $this->user->id
                ],
                'reactions' => ReactionCollection::make($this->reactions),
                'parent' => $this->getParentCommentData(),
            ],
            'date' => [
                'iso' => $this->created_at->getIso(),
                'time_ago' => $this->created_at->getTimeAgo()
            ],
            'meta' => [
                'permissions' => [
                    'can_edit' => auth_check() ? me()->can('update', $this->resource) : false,
                    'can_delete' => auth_check() ? me()->can('delete', $this->resource) : false,
                    'can_report' => auth_check() && (int) me()->id !== (int) $this->user_id,
                ],
                'is_translatable' => $this->isContentTranslatable()
            ]
        ];
    }

    private function getParentCommentData()
    {
        if($this->parent) {
            return [
                'content' => Str::limit($this->parent->content, 120),
                'user' => [
                    'name' => $this->parent->user->name,
                    'username' => $this->parent->user->username,
                    'id' => $this->parent->user->id
                ]
            ];
        }
    }
}
