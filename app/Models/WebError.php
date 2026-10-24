<?php

namespace App\Models;

use Database\Factories\WebErrorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An uncaught JavaScript error on the public website.
 */
class WebError extends Model
{
    /** @use HasFactory<WebErrorFactory> */
    use HasFactory, MassPrunable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'column' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * What groups occurrences of "the same" error on the Errors page.
     */
    public static function fingerprintFor(string $message, ?string $source, ?int $line): string
    {
        return sha1($message.'|'.($source ?? '').'|'.($line ?? ''));
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
     * @return BelongsTo<WebPageview, $this>
     */
    public function pageview(): BelongsTo
    {
        return $this->belongsTo(WebPageview::class, 'web_pageview_id');
    }
}
