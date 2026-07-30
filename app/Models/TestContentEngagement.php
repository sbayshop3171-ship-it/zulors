<?php

namespace App\Models;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;

class TestContentEngagement extends Model
{
	protected $table = Table::TEST_CONTENT_ENGAGEMENTS;

	protected $fillable = [
		'campaign_key',
		'user_id',
		'post_id',
		'comment_id',
		'reaction_unified_id',
		'status',
		'error_message',
		'published_at',
	];

	protected $casts = [
		'published_at' => 'datetime',
	];
}
