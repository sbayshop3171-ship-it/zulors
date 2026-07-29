<?php

namespace App\Models;

use App\Support\Num;
use App\Enums\Job\JobType;
use App\Enums\Job\JobStatus;
use App\Enums\Job\JobApproval;
use App\Models\Traits\View\Viewable;
use App\Support\Casts\ModelTimestampCast;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\Base\SupportsHashIds;
use App\Models\Traits\Bookmark\Bookmarkable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JobListing extends Model
{

    use Bookmarkable,
		Viewable,
		SupportsHashIds,
		HasFactory;

    protected $casts = [
        'type' => JobType::class,
        'approval' => JobApproval::class,
        'status' => JobStatus::class,
        'is_start_income' => 'boolean',
        'is_urgent' => 'boolean',
        'is_remote' => 'boolean',
        'created_at' => ModelTimestampCast::class,
        'last_contacted_at' => ModelTimestampCast::class,
    ];

    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('status', JobStatus::ACTIVE);
    }

    public function scopeApproved($query)
	{
		return $query->where('approval', JobApproval::APPROVED);
	}

    public function scopeListable($query)
	{
		return $query->active()->approved();
	}

    public function scopeWithRelations($query)
    {
        return $query->with(['category', 'user' => function($query) {
            $query->select(['id', 'first_name', 'last_name', 'username', 'avatar', 'caption', 'verified']);
        }]);
    }

    public function scopeFilter($query, array $filterOptions)
	{
		if(! empty($filterOptions['cursor'])) {
			$query->where('id', '<', $filterOptions['cursor']);
		}

        if(! empty($filterOptions['query'])) {
			$term = "%{$filterOptions['query']}%";
			
			$query->where(function ($query) use ($term) {
				$query->where('title', 'LIKE', $term)
                    ->orWhere('overview', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
			});
		}

        if(! empty($filterOptions['category_id'])) {
			$query->where('category_id', $filterOptions['category_id']);
		}
    }

    public function scopeExcludeDraft($query)
    {
        return $query->where('status', '!=', JobStatus::DRAFT);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function getFormattedIncomeAttribute()
    {
        return Num::currency($this->income, $this->currency);
    }

    public function getUrlAttribute()
    {
        $hashId = encode_id($this->id);
        
        return url("jobs/{$hashId}");
    }

    public function getCategoryNameAttribute()
	{
		if(empty($this->category)) {
			return __('labels.uncategorized');
		}

		return $this->category->category_name;
	}

    public function getCurrencyNameAttribute()
	{
		return Num::currencyName($this->currency);
	}
}
