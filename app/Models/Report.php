<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\Report\ReportType;
use Illuminate\Database\Eloquent\Model;
use App\Services\Feedback\ReportService;
use App\Support\Casts\ModelTimestampCast;

class Report extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => ReportStatus::class,
        'created_at' => ModelTimestampCast::class,
        'updated_at' => ModelTimestampCast::class,
        'type' => ReportType::class,
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id', 'id');
    }

    public function reportable()
    {
        return $this->morphTo();
    }

    public function getReasonAttribute()
    {
        $reasons = (new ReportService($this->type->value))->getReasons();

        return $reasons['reasons'][$this->reason_index] ?? null;
    }
}
