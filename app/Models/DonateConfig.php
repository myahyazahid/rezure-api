<?php

namespace App\Models;

use Database\Factories\DonateConfigFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonateConfig extends Model
{
    /** @use HasFactory<DonateConfigFactory> */
    use HasFactory;

    /**
     * Largest QRIS image accepted, in bytes. Bank-issued QRIS exports are
     * usually a few hundred KB as PNG; this leaves room for the larger ones.
     */
    public const MAX_QRIS_BYTES = 1_048_576;

    /**
     * Format → the `Content-Type` it is served with. A closed list: anything
     * else is rejected at upload, so a stored `qris_format` is always a key here.
     *
     * @var array<string, string>
     */
    public const QRIS_MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /**
     * The `qris_*` columns only ever come from the uploaded file (see
     * `Dashboard\DonateController::updateQris()`), never from a request field.
     */
    protected $fillable = [
        'message',
        'qris_path',
        'qris_format',
        'qris_size',
        'qris_sha256',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qris_size' => 'integer',
        ];
    }

    /**
     * The QRIS is optional — a config without one is the normal state, and
     * the API then sends `qris: null`.
     */
    public function hasQris(): bool
    {
        return $this->qris_path !== null;
    }

    public function qrisMimeType(): string
    {
        return self::QRIS_MIME_TYPES[$this->qris_format];
    }

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
