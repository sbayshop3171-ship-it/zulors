<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Product\ProductType;
use App\Enums\Product\ProductStatus;
use App\Models\Traits\View\Viewable;
use App\Support\Casts\ModelTimestampCast;
use App\Enums\Product\ProductApproval;
use App\Enums\Product\ProductCondition;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Base\SupportsHashIds;
use App\Models\Traits\Bookmark\Bookmarkable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
	use Bookmarkable,
		Viewable,
		SupportsHashIds,
		HasFactory;
	
	public $casts = [
		'status' => ProductStatus::class,
		'approval' => ProductApproval::class,
		'type' => ProductType::class,
		'condition' => ProductCondition::class,
		'is_store' => 'boolean',
		'created_at' => ModelTimestampCast::class,
		'last_contacted_at' => ModelTimestampCast::class,
	];

	public $guarded = [];

	public function scopeWithRelations($query)
	{
		return $query->with(['media', 'category', 'store', 'user' => function($query) {
            $query->select(['id', 'first_name', 'last_name', 'username', 'avatar', 'caption', 'verified']);
        }]);
	}

	public function scopeActive($query)
	{
		return $query->whereNot('status', ProductStatus::DRAFT);
	}

	public function scopeApproved($query)
	{
		return $query->where('approval', ProductApproval::APPROVED);
	}

	public function scopeListable($query)
	{
		return $query->where('status', ProductStatus::ACTIVE)->approved()->where('stock_quantity', '>', 0);
	}

	public function scopeFilter($query, array $filterOptions)
	{
		if(! empty($filterOptions['cursor'])) {
			$query->where('id', '<', $filterOptions['cursor']);
		}

		if(! empty($filterOptions['query'])) {
			$term = "%{$filterOptions['query']}%";
			
			$query->where(function ($query) use ($term) {
				$query->where('title', 'LIKE', $term)->orWhere('description', 'LIKE', $term);
			});
		}

		if(! empty($filterOptions['category_id'])) {
			$query->where('category_id', $filterOptions['category_id']);
		}

		if(! empty($filterOptions['is_store'])) {
			$query->whereNotNull('store_id');
		}

		if(! empty($filterOptions['with_discount'])) {
			$query->where('discount', '>', 0);
		}

		if(! empty($filterOptions['high_rating'])) {
			$query->where('rating', '>=', 4);
		}

			if(! empty($filterOptions['currencies'])) {
				$currencies = array_keys(array_filter($filterOptions['currencies']));

				if(! empty($currencies)) {
					$query->whereIn('currency', $currencies);
				}
			}

			if(! empty($filterOptions['conditions'])) {
				$conditions = ProductCondition::tryFromArray(array_keys(array_filter($filterOptions['conditions'])));

				if(! empty($conditions)) {
					$query->whereIn('condition', array_map(fn($condition) => $condition->value, $conditions));
				}
			}

			if(! empty($filterOptions['types'])) {
				$types = ProductType::tryFromArray(array_keys(array_filter($filterOptions['types'])));

				if(! empty($types)) {
					$query->whereIn('type', array_map(fn($type) => $type->value, $types));
				}
			}

			if(! empty($filterOptions['price']['from'])) {
				$query->where('price', '>=', $filterOptions['price']['from']);
			}

			if(! empty($filterOptions['price']['to'])) {
				$query->where('price', '<=', $filterOptions['price']['to']);
			}

			return $query;
		}

	public function user()
	{
		return $this->belongsTo(User::class, 'user_id', 'id');
	}

	public function store()
	{
		return $this->belongsTo(Store::class, 'store_id', 'id');
	}

	public function category()
	{
		return $this->belongsTo(Category::class, 'category_id', 'id');
	}

	public function media()
    {
        return $this->morphMany(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id');
    }

	public function getFormattedPriceAttribute()
	{
		return Num::currency($this->price, $this->currency);
	}

	public function getSalePriceAttribute(): float
    {
		$price = (string) $this->price;
        $discountPercentage = is_numeric($this->discount) ? (string) $this->discount : '0';

        $discountRate = bcdiv($discountPercentage, '100');
		$discountAmount = bcmul($price, $discountRate);

        return bcsub($price, $discountAmount);
    }

	public function getCurrencyNameAttribute()
	{
		return Num::currencyName($this->currency);
	}

	public function getFormattedSalePriceAttribute()
	{
		return Num::currency($this->sale_price, $this->currency);
	}

	public function getFormattedDiscountAmountAttribute()
	{
		if(empty($this->discount)) {
			return Num::currency(0, $this->currency);
		}
		else {
			$discountAmount = bcdiv(bcmul($this->price, $this->discount), 100);
			return Num::currency($discountAmount, $this->currency);
		}
	}

	public function getCategoryNameAttribute()
	{
		if(empty($this->category)) {
			return __('labels.uncategorized');
		}

		return $this->category->category_name;
	}

	public function getPreviewImageUrlAttribute()
	{
		$media = $this->media;

		if($media->isEmpty()) {
			return asset(config('marketplace.product.default_preview'));
		}

		return $media->first()->source_url;
	}

	public function getUrlAttribute()
	{
		return url("marketplace/product/{$this->hash_id}");
	}

	public function getHasStoreAttribute()
	{
		return empty($this->store_id) ? false : true;
	}

	public function hasDiscount()
	{
		return ((int) $this->discount > 0);
	}
}
