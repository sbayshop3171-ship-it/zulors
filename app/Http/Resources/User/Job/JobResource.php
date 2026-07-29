<?php

namespace App\Http\Resources\User\Job;

use App\Support\Num;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = me()->id === $this->user_id;

        return [
            'id' => $this->id,
            'hash_id' => $this->hash_id,
            'title' => $this->title,
            'overview' => $this->overview,
            'description' => $this->description,
            'location' => $this->location,
            'currency' => [
                'symbol' => $this->currency
            ],
            'views_count' => [
                'raw' => $this->views_count,
                'formatted' => Num::abbreviate($this->views_count)
            ],
            'bookmarks_count' => [
                'raw' => $this->bookmarks_count,
                'formatted' => Num::abbreviate($this->bookmarks_count)
            ],
            'income' => [
                'raw' => $this->income,
                'formatted' => $this->formatted_income
            ],
            'is_start_income' => $this->is_start_income,
            'is_urgent' => $this->is_urgent,
            'is_remote' => $this->is_remote,
            'type' => [
                'key' => $this->type,
                'label' => $this->type->label()
            ],
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'relations' => [
                'user' => UserPreviewResource::make($this->user)
            ],
            'meta' => [
                'activity' => [
                    'bookmarked' => $this->isBookmarkedBy(me()->id)
                ],
                'is_owner' => $isOwner,
                'permissions' => [
                    'can_report' => empty($isOwner),
                    'can_apply' => empty($isOwner)
                ]
            ],
            'date' => [
                'timestamp' => $this->created_at->getTimestamp(),
                'iso' => $this->created_at->getIso(),
                'time_ago' => $this->created_at->getTimeAgo()
            ]         
        ];
    }
}
