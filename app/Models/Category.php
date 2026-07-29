<?php

namespace App\Models;

use App\Enums\Category\CategoryType;
use App\Enums\Category\CategoryStatus;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;

    public $casts = [
        'categorizable_type' => CategoryType::class,
        'localization' => 'array',
        'status' => CategoryStatus::class
    ];

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $this->where('status', CategoryStatus::ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $this->where('status', CategoryStatus::INACTIVE);
    }

    public function scopeDraft($query)
    {
        return $this->where('status', CategoryStatus::DRAFT);
    }

    public function scopeDraftable($query)
    {
        return $this->where('status', CategoryStatus::DRAFT);
    }

    public function scopeMarketplace()
    {
        return $this->where('categorizable_type', CategoryType::PRODUCT)->whereNull('parent_id');    
    }

    public function scopeJobs()
    {
        return $this->where('categorizable_type', CategoryType::JOB)->whereNull('parent_id');    
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id', 'id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    public function jobs()
    {
        return $this->hasMany(JobListing::class, 'category_id', 'id');
    }

    public function getCategoryNameAttribute()
    {
        return $this->fetchName($this->localization);
    }

    public function getUsageCountAttribute()
    {
        if($this->categorizable_type->isProduct()) {
            return $this->products()->count();
        }
        else if($this->categorizable_type->isJob()) {
            return $this->jobs()->count();
        }

        return 0;
    }

    public static function getMarketplaceCategories()
    {
        return self::query()->active()->marketplace()->select('id', 'localization')->get()->map(function ($item) {
            return [
                'key' => $item->id,
                'value' => $item->fetchName($item->localization)
            ];
        })->toArray();
    }

    public static function getJobCategories()
    {
        return self::query()->active()->jobs()->select('id', 'localization')->get()->map(function ($item) {
            return [
                'key' => $item->id,
                'value' => $item->fetchName($item->localization)
            ];
        })->toArray();
    }

    private function fetchName(array $localization)
    {
        $locale = app()->getLocale();
        
        $name = (isset($localization[$locale])) ? $localization[$locale] : reset($localization);

        return (empty($name)) ? __('labels.unknown') : $name;
    }
}
