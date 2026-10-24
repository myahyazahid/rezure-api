<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebDailySummary extends Model
{
    protected $table = 'web_daily_summary';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'visitors' => 'integer',
            'new_visitors' => 'integer',
            'sessions' => 'integer',
            'engaged_sessions' => 'integer',
            'bounces' => 'integer',
            'pageviews' => 'integer',
            'total_duration_seconds' => 'integer',
            'total_engaged_seconds' => 'integer',
            'downloads' => 'integer',
            'conversions' => 'integer',
        ];
    }
}
