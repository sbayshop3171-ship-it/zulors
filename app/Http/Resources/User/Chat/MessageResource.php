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

namespace App\Http\Resources\User\Chat;

use App\Http\Resources\User\Media\MediaResource;
use App\Http\Resources\User\Morph\LinkSnapshotResource;
use App\Http\Resources\User\Timeline\ReactionCollection;
use App\Http\Resources\User\User\UserPreviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $messageData = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'chat_uuid' => $this->chat_uuid,
            'content' => $this->content,
            'type' => $this->type,
            'has_parent' => (empty($this->parent_id)) ? false : true,
            'relations' => [
                'user' => UserPreviewResource::make($this->user),
                'reactions' => ReactionCollection::make($this->reactions),
                'parent' => $this->getParentMessageData(),
                'participant' => [
                    'color' => $this->participant->metadata['color']
                ],
                'media' => []
            ],
            'date' => [
                'iso' => $this->created_at->getIso(),
                'time_ago' => $this->created_at->getTimeAgo(),
                'generic' => $this->created_at->getGeneric(),
                'date' => $this->created_at->getDate()
            ],
            'meta' => [
                'is_deleted' => $this->is_deleted,
                'permissions' => [
                    'can_edit' => true,
                    'can_delete' => true
                ],
                'is_translatable' => $this->isMessageTranslatable(),
                'client_uid' => $this->client_uid ?? null,
            ]
        ];

        if($this->media) {
            $messageData['relations']['media'] = MediaResource::make($this->media);
        }

        if($this->linkSnapshot) {
            $messageData['relations']['link_snapshot'] = LinkSnapshotResource::make($this->linkSnapshot);
        }

        return $messageData;
    }

    private function getParentMessageData()
    {
        if($this->parent) {
            return [
                'content' => Str::limit($this->parent->content, 120),
                'type' => $this->parent->type,
                'relations' => [
                    'media' => MediaResource::make($this->parent->media)
                ],
                'user' => [
                    'name' => $this->parent->user->name,
                    'username' => $this->parent->user->username,
                    'id' => $this->parent->user->id,
                    'avatar_url' => $this->parent->user->avatar_url,
                    'verified' => $this->parent->user->verified
                ],
                'participant' => [
                    'color' => $this->parent->participant->metadata['color']
                ],
                'link_snapshot' => (! empty($this->parent->linkSnapshot)) ? LinkSnapshotResource::make($this->parent->linkSnapshot) : null
            ];
        }
    }
}
