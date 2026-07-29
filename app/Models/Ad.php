<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Ad\AdStatus;
use App\Enums\Ad\AdApproval;
use Illuminate\Database\Eloquent\Model;
use App\Support\Casts\ModelTimestampCast;

class Ad extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => AdStatus::class,
        'approval' => AdApproval::class,
        'target_topics' => 'array',
        'created_at' => ModelTimestampCast::class,
        'last_show_at' => ModelTimestampCast::class,
        'last_charge_at' => ModelTimestampCast::class
    ];

    public function scopeApproved($query)
    {
        return $query->where('approval', AdApproval::APPROVED);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function scopeExcludeDraft($query)
    {
        return $query->where('status', '!=', AdStatus::DRAFT);
    }

    public function scopePublished($query)
    {
        return $query->where('status', AdStatus::PUBLISHED);
    }

    public function media()
    {
        return $this->morphMany(Media::class, 'mediaable', 'mediaable_type', 'mediaable_id', 'id', 'id');
    }

    public function impressions()
    {
        return $this->hasMany(AdImpression::class, 'ad_id', 'id');
    }

    public function getPreviewImageUrlAttribute()
	{
		$media = $this->media;

		if($media->isEmpty()) {
			return asset(config('ads.ad.default_preview'));
		}

		return $media->first()->source_url;
	}

    public function getFormattedIdAttribute(): string
    {
        return Num::leadingZero($this->id);
    }

    public function getFormattedSpentBudgetAttribute(): string
    {
        return Num::currency($this->spent_budget);
    }

    public function getFormattedTotalBudgetAttribute(): string
    {
        return Num::currency($this->total_budget);
    }

    public function getFormattedViewsCountAttribute(): string
    {
        return Num::abbreviate($this->views_count);
    }

    public function getFormattedClicksCountAttribute(): string
    {
        return Num::abbreviate($this->clicks_count);
    }

    public function getRemainingBudgetAttribute(): float
    {
        return max(0, round(((float) $this->total_budget - (float) $this->spent_budget), 2));
    }

    public function getFormattedRemainingBudgetAttribute(): string
    {
        return Num::currency($this->remaining_budget);
    }

    public function getTargetTopicsTextAttribute(): string
    {
        return collect($this->target_topics ?: [])->join(', ');
    }

    public function getFormattedPricePerViewAttribute(): string
    {
        return Num::currency($this->price_per_view);
    }
}
