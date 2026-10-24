<?php

namespace App\Models;

use Database\Factories\WebVisitorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One browser on the public website, identified by the tracker's persistent
 * `_rz_vid` id (v4 website analytics).
 */
class WebVisitor extends Model
{
    /** @use HasFactory<WebVisitorFactory> */
    use HasFactory, MassPrunable;

    protected $fillable = [
        'visitor_uuid',
        'first_seen_at',
        'last_seen_at',
        'sessions_count',
        'pageviews_count',
        'first_landing_path',
        'first_referrer_host',
        'first_channel',
        'first_source',
        'first_utm_source',
        'first_utm_medium',
        'first_utm_campaign',
        'last_ip_address',
        'last_country_code',
        'last_city',
        'last_browser',
        'last_os',
        'last_device_type',
        'first_download_at',
        'device_id',
        'device_linked_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'first_download_at' => 'datetime',
            'device_linked_at' => 'datetime',
            'sessions_count' => 'integer',
            'pageviews_count' => 'integer',
        ];
    }

    /**
     * Visitors not seen within the retention window go, and their sessions,
     * pageviews and events with them (cascading foreign keys).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('last_seen_at', '<', now()->subDays(config('web-analytics.retention_days')));
    }

    /**
     * Same shortening the dashboard uses for desktop devices.
     */
    protected function shortId(): Attribute
    {
        return Attribute::get(fn (): string => 'vis_'.substr(str_replace('-', '', $this->visitor_uuid), 0, 8));
    }

    /**
     * @return HasMany<WebSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(WebSession::class);
    }

    /**
     * @return HasMany<WebPageview, $this>
     */
    public function pageviews(): HasMany
    {
        return $this->hasMany(WebPageview::class);
    }

    /**
     * @return HasMany<WebEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WebEvent::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
