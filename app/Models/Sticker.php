<?php

namespace App\Models;

use Database\Factories\StickerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sticker extends Model
{
    /** @use HasFactory<StickerFactory> */
    use HasFactory;

    /**
     * Largest file accepted, in bytes. The desktop client enforces the same
     * limit when downloading — keep the two in step (`MAX_STICKER_BYTES` in
     * `rezureapp`'s `services::sticker_library`).
     */
    public const MAX_BYTES = 262_144;

    /**
     * Format → the `Content-Type` it is served with. A closed list: anything
     * else is rejected at upload, so a stored `format` is always a key here.
     *
     * @var array<string, string>
     */
    public const MIME_TYPES = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    /**
     * Rows only ever carry what the dashboard form or the upload handler
     * sets — `slug`, `format`, `file_path`, `size` and `sha256` all come from
     * the uploaded file, never from a request field.
     */
    protected $fillable = [
        'slug',
        'name',
        'category',
        'format',
        'file_path',
        'size',
        'sha256',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * What `GET /api/v1/stickers` offers.
     *
     * @param  Builder<Sticker>  $query
     * @return Builder<Sticker>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function mimeType(): string
    {
        return self::MIME_TYPES[$this->format];
    }
}
