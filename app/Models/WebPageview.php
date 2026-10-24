<?php

namespace App\Models;

use Database\Factories\WebPageviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebPageview extends Model
{
    /** @use HasFactory<WebPageviewFactory> */
    use HasFactory, MassPrunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'sequence' => 'integer',
            'engaged_ms' => 'integer',
            'max_scroll_pct' => 'integer',
            'scroll_milestones' => 'array',
            'is_entry' => 'boolean',
            'is_exit' => 'boolean',
            'is_not_found' => 'boolean',
            'lcp_ms' => 'integer',
            'inp_ms' => 'integer',
            'cls' => 'float',
            'fcp_ms' => 'integer',
            'ttfb_ms' => 'integer',
            'dom_load_ms' => 'integer',
            'page_load_ms' => 'integer',
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
     * @return BelongsTo<WebSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(WebSession::class, 'web_session_id');
    }

    /**
     * @return BelongsTo<WebVisitor, $this>
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(WebVisitor::class, 'web_visitor_id');
    }

    /**
     * @return HasMany<WebEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WebEvent::class);
    }
}
