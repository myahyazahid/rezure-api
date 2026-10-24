<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebBotHitSummary extends Model
{
    protected $table = 'web_bot_hit_summary';

    protected $fillable = [
        'date',
        'bot_name',
        'bot_category',
        'hits',
    ];

    protected function casts(): array
    {
        return [
            // Same reasoning as HourlyTrafficSummary: keep the stored value
            // comparable to plain Y-m-d strings.
            'date' => 'date:Y-m-d',
            'hits' => 'integer',
        ];
    }
}
