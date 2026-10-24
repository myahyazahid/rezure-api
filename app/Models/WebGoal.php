<?php

namespace App\Models;

use Database\Factories\WebGoalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A conversion the dashboard measures: an event (by name, optionally label)
 * or a pageview whose path matches a `*` wildcard pattern.
 */
class WebGoal extends Model
{
    /** @use HasFactory<WebGoalFactory> */
    use HasFactory;

    public const TYPE_EVENT = 'event';

    public const TYPE_PAGEVIEW = 'pageview';

    protected $fillable = [
        'name',
        'type',
        'match',
        'label_match',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The escape character used with wildcardToLike() — `LIKE ? ESCAPE '!'`.
     * Not a backslash: MySQL and sqlite disagree on how to write one inside
     * an ESCAPE clause.
     */
    public const LIKE_ESCAPE = '!';

    /**
     * `*` wildcard pattern → SQL LIKE pattern, with LIKE's own wildcards
     * escaped so a literal `_` in a path doesn't match any character.
     */
    public static function wildcardToLike(string $pattern): string
    {
        return str_replace(['!', '%', '_', '*'], ['!!', '!%', '!_', '%'], $pattern);
    }
}
