<?php

namespace App\Models;

use Database\Factories\WebClickFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebClick extends Model
{
    /** @use HasFactory<WebClickFactory> */
    use HasFactory, MassPrunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'x_pct' => 'float',
            'y_px' => 'integer',
            'page_height' => 'integer',
            'viewport_width' => 'integer',
            'is_rage' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('occurred_at', '<', now()->subDays(config('web-analytics.retention_days')));
    }

    /**
     * @return BelongsTo<WebPageview, $this>
     */
    public function pageview(): BelongsTo
    {
        return $this->belongsTo(WebPageview::class, 'web_pageview_id');
    }

    /**
     * @return BelongsTo<WebSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(WebSession::class, 'web_session_id');
    }
}
