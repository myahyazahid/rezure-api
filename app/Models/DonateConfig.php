<?php

namespace App\Models;

use Database\Factories\DonateConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonateConfig extends Model
{
    /** @use HasFactory<DonateConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'message',
        'local',
        'global',
        'crypto',
    ];

    protected function casts(): array
    {
        return [
            'local' => 'array',
            'global' => 'array',
            'crypto' => 'array',
        ];
    }

    /**
     * Single-row settings, not an append-only history — creates the row
     * with harmless defaults on first read so both the API and the
     * dashboard edit form always have something to work with.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'message' => 'Rezure is free & open-source — support keeps it going.',
            'local' => [],
            'global' => [],
            'crypto' => [],
        ]);
    }
}
