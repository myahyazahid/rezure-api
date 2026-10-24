<?php

namespace App\Models;

use Database\Factories\WebFunnelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved multi-step funnel. `steps` is an ordered list of
 * `{type: event|pageview, match: string, label: ?string}`.
 */
class WebFunnel extends Model
{
    /** @use HasFactory<WebFunnelFactory> */
    use HasFactory;

    public const SCOPE_SESSION = 'session';

    public const SCOPE_VISITOR = 'visitor';

    protected $fillable = [
        'name',
        'steps',
        'scope',
        'window_hours',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'window_hours' => 'integer',
        ];
    }
}
