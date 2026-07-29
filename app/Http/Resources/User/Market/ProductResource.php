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

namespace App\Http\Resources\User\Market;

use App\Support\Num;
use Illuminate\Http\Request;
use App\Http\Resources\User\Media\MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\User\UserPreviewResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isOwner = (me()->id === $this->user_id);
        
        $item = [
            'id' => $this->id,
            'hash_id' => $this->hash_id,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->category_name,
            'preview_image_url' => $this->preview_image_url,
            'relations' => [
                'merchant' => [],
                'media' => $this->media->map(function($mediaItem) {
                    return MediaResource::make($mediaItem);
                })
            ],
            'sale_price' => null,
            'price' => [
                'raw' => $this->price,
                'formatted' => $this->formatted_price
            ],
            'condition' => $this->condition->label(),
            'currency' => [
                'symbol' => $this->currency
            ],
            'stock_quantity' => $this->stock_quantity,
            'discount' => $this->discount,
            'address' => $this->address,
            'views_count' => [
                'raw' => $this->views_count,
                'formatted' => Num::abbreviate($this->views_count)
            ],
            'contacts_count' => [
                'raw' => $this->contacts_count,
                'formatted' => Num::abbreviate($this->contacts_count)
            ],
            'reviews_count' => [
                'raw' => $this->reviews_count,
                'formatted' => Num::abbreviate($this->reviews_count)
            ],
            'bookmarks_count' => [
                'raw' => $this->bookmarks_count,
                'formatted' => Num::abbreviate($this->bookmarks_count)
            ],
            'meta' => [
                'activity' => [
                    'bookmarked' => $this->isBookmarkedBy(me()->id)
                ],
                'is_owner' => $isOwner
            ],
            'date' => [
                'timestamp' => $this->created_at->getTimestamp(),
                'iso' => $this->created_at->getIso(),
                'time_ago' => $this->created_at->getTimeAgo()
            ]
        ];

        $item['relations']['merchant'] = UserPreviewResource::make($this->user, [
            'is_store' => false
        ]);

        if($this->hasDiscount()) {
            $item['sale_price'] = [
                'raw' => $this->sale_price,
                'formatted' => $this->formatted_sale_price
            ];
        }

        return $item;
    }
}
