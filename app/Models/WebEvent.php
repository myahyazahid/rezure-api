<?php

namespace App\Models;

use Database\Factories\WebEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebEvent extends Model
{
    /** @use HasFactory<WebEventFactory> */
    use HasFactory, MassPrunable;

    /**
     * Recorded server-side by GET /site/download — the website's main
     * conversion, and what "downloads" means everywhere in the dashboard.
     */
    public const DOWNLOAD = 'download';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'props' => 'array',
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
     * @return BelongsTo<WebPageview, $this>
     */
    public function pageview(): BelongsTo
    {
        return $this->belongsTo(WebPageview::class, 'web_pageview_id');
    }
}
