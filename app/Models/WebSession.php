<?php

namespace App\Models;

use Database\Factories\WebSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One visit to the website — ends after 30 minutes without activity, or
 * when the visitor lands again with different UTM parameters (decided by
 * the tracker, which owns `session_uuid`).
 */
class WebSession extends Model
{
    /** @use HasFactory<WebSessionFactory> */
    use HasFactory, MassPrunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'engaged_seconds' => 'integer',
            'pageviews_count' => 'integer',
            'events_count' => 'integer',
            'is_engaged' => 'boolean',
            'is_bounce' => 'boolean',
            'has_conversion' => 'boolean',
            'is_new_visitor' => 'boolean',
            'visit_number' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'pixel_ratio' => 'float',
            'memory_gb' => 'float',
            'languages' => 'array',
            'do_not_track' => 'boolean',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('started_at', '<', now()->subDays(config('web-analytics.retention_days')));
    }

    /**
     * @return BelongsTo<WebVisitor, $this>
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(WebVisitor::class, 'web_visitor_id');
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
     * @return HasMany<WebClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(WebClick::class);
    }

    /**
     * @return HasMany<WebError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(WebError::class);
    }
}
