<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebDailyPageSummary extends Model
{
    protected $table = 'web_daily_page_summary';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'pageviews' => 'integer',
            'visitors' => 'integer',
            'entries' => 'integer',
            'exits' => 'integer',
            'bounces' => 'integer',
            'total_engaged_ms' => 'integer',
            'total_scroll_pct' => 'integer',
            'not_found' => 'integer',
        ];
    }
}
