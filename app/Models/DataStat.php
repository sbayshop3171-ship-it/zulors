<?php

namespace App\Models;

use App\Enums\Media\MediaType;
use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DataStat extends Model
{
    use HasFactory;

    protected $casts = [
        'media_type' => MediaType::class
    ];

    public $fillable = [
        'disk',
        'media_type',
        'content_size',
        'content_items'
    ];

    public $table = Table::DATA_STATS;
}
