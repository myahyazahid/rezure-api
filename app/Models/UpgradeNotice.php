<?php

namespace App\Models;

use Database\Factories\UpgradeNoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpgradeNotice extends Model
{
    /** @use HasFactory<UpgradeNoticeFactory> */
    use HasFactory;

    protected $fillable = [
        'enabled',
        'major',
        'message',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'major' => 'integer',
        ];
    }

    /**
     * Single-row settings, like `DonateConfig` — one announcement at a time,
     * of the newest major line. Starts switched off, so nothing is announced
     * until a maintainer turns it on from the Releases page.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['enabled' => false]);
    }

    /**
     * Only clients on an older line see the notice — one already on the
     * announced major (or newer) is kept current by `/version/latest`
     * instead. A caller that didn't say which line it's on (`null`) sees it
     * whenever it's switched on.
     */
    public function isShownTo(?int $clientMajor): bool
    {
        if (! $this->enabled || $this->major === null || $this->url === null) {
            return false;
        }

        return $clientMajor === null || $clientMajor < $this->major;
    }
}
