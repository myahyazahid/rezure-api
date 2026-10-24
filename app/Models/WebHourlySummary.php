<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebHourlySummary extends Model
{
    protected $table = 'web_hourly_summary';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'hour' => 'integer',
            'visitors' => 'integer',
            'sessions' => 'integer',
            'pageviews' => 'integer',
        ];
    }
}
