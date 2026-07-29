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

namespace App\Http\Resources\User\Timeline\Editor;

use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\MediaApi\Giphy\Giphy;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\Morph\LinkSnapshotResource;

class DraftPostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $apiData = [
            'id' => $this->resource->id,
            'content' => $this->resource->content,
            'type' => $this->resource->type,
            'relations' => [
                'media' => $this->resource->media->map(function($item) {
                    return MediaResource::make($item);
                }),
                'poll' => $this->resource->poll,
                'link_snapshot' => $this->getLinkSnapshot()
            ]
        ];

        return $apiData;
    }

    private function getLinkSnapshot()
    {
        $linkSnapshot = $this->resource->linkSnapshot;

        if($linkSnapshot) {
            return LinkSnapshotResource::make($linkSnapshot);
        }

        return null;
    }
}
