<?php

namespace App\Models;

use Database\Factories\CountryTrafficSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryTrafficSummary extends Model
{
    /** @use HasFactory<CountryTrafficSummaryFactory> */
    use HasFactory;

    protected $table = 'country_traffic_summary';

    protected $fillable = [
        'date',
        'country_code',
        'device_count',
    ];

    protected function casts(): array
    {
        return [
            // Explicit Y-m-d format keeps the stored value comparable to the
            // plain date strings used in updateOrCreate lookups — the bare
            // 'date' cast serializes with a time component on write.
            'date' => 'date:Y-m-d',
            'device_count' => 'integer',
        ];
    }
}
