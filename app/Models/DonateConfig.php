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
    ];

    /**
     * Single-row settings, not an append-only history. Also the source of
     * `GET /api/v1/support/donate`'s `Last-Modified` — every mutation to a
     * `DonateMethod` touches this row too (see `Dashboard\DonateController`)
     * so the timestamp reflects the methods list, not just the message.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'message' => 'Rezure is free & open-source — support keeps it going.',
        ]);
    }
}
