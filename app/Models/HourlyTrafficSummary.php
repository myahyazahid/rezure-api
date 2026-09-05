<?php

namespace App\Models;

use Database\Factories\HourlyTrafficSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HourlyTrafficSummary extends Model
{
    /** @use HasFactory<HourlyTrafficSummaryFactory> */
    use HasFactory;

    protected $table = 'hourly_traffic_summary';

    protected $fillable = [
        'date',
        'hour',
        'event_count',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format keeps the stored value comparable to the
            // plain date strings used in updateOrCreate lookups — the bare
            // 'date' cast serializes with a time component on write.
            'date' => 'date:Y-m-d',
            'hour' => 'integer',
            'event_count' => 'integer',
        ];
    }
}
