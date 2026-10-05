<?php

namespace App\Models;

use Database\Factories\DonateMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonateMethod extends Model
{
    /** @use HasFactory<DonateMethodFactory> */
    use HasFactory;

    /**
     * Largest icon accepted, in bytes. Coin and brand logos are tiny; this
     * is generous.
     */
    public const MAX_ICON_BYTES = 262_144;

    /**
     * Format → the `Content-Type` it is served with. A closed list: anything
     * else is rejected at upload, so a stored `icon_format` is always a key here.
     *
     * @var array<string, string>
     */
    public const ICON_MIME_TYPES = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /**
     * One row per configured donate button/wallet — `category` (`local`,
     * `global`, `crypto`) is which array of `GET /api/v1/support/donate`'s
     * response it serializes into, `preset` names which known platform it
     * was created from (see `App\Support\DonatePresets`), or `'custom'` for
     * a maintainer-typed one.
     *
     * The `icon_*` columns only ever come from the uploaded file (see
     * `Dashboard\DonateController::storeIcon()`), never from a request field.
     */
    protected $fillable = [
        'category',
        'preset',
        'label',
        'url',
        'symbol',
        'network',
        'address',
        'icon_path',
        'icon_format',
        'icon_size',
        'icon_sha256',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon_size' => 'integer',
        ];
    }

    public function hasIcon(): bool
    {
        return $this->icon_path !== null;
    }

    public function iconMimeType(): string
    {
        return self::ICON_MIME_TYPES[$this->icon_format];
    }
}
