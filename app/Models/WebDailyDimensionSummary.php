<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebDailyDimensionSummary extends Model
{
    protected $table = 'web_daily_dimension_summary';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'visitors' => 'integer',
            'sessions' => 'integer',
            'pageviews' => 'integer',
            'bounces' => 'integer',
            'total_duration_seconds' => 'integer',
            'conversions' => 'integer',
        ];
    }
}
