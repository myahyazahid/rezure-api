<?php

namespace App\Services\WebAnalytics;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The period, comparison and dimension filters every website analytics page
 * shares, read from the query string so any view of the dashboard is a URL
 * that can be bookmarked or shared:
 *
 *     ?period=30d&compare=previous&f[country]=ID&f[channel]=Social
 *
 * Dates are display-timezone calendar days; the UTC bounds the queries use
 * come from fromUtc()/toUtc().
 */
final class WebAnalyticsFilters
{
    /**
     * @var array<string, string>
     */
    public const PERIODS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        '12m' => 'Last 12 months',
        'custom' => 'Custom',
    ];

    /**
     * @var array<string, string>
     */
    public const COMPARISONS = [
        'previous' => 'Previous period',
        'year' => 'Same period last year',
        'none' => 'No comparison',
    ];

    /**
     * @var list<string>
     */
    public const GRANULARITIES = ['hour', 'day', 'week', 'month'];

    /**
     * @param  array<string, string>  $dimensions
     */
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $period = '30d',
        public readonly string $compare = 'previous',
        public readonly array $dimensions = [],
        private readonly ?string $requestedGranularity = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $timezone = config('app.display_timezone');
        $today = now($timezone)->startOfDay();
        $period = array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : '30d';

        [$from, $to] = match ($period) {
            'today' => [$today->copy(), $today->copy()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            '7d' => [$today->copy()->subDays(6), $today->copy()],
            '90d' => [$today->copy()->subDays(89), $today->copy()],
            '12m' => [$today->copy()->subMonthsNoOverflow(12)->addDay(), $today->copy()],
            'custom' => self::customRange($request, $today),
            default => [$today->copy()->subDays(29), $today->copy()],
        };

        $compare = array_key_exists((string) $request->query('compare'), self::COMPARISONS) ? (string) $request->query('compare') : 'previous';

        $dimensions = [];

        foreach ((array) $request->query('f', []) as $key => $value) {
            if (is_string($key) && in_array($key, WebDimensions::filterKeys(), true) && is_string($value) && $value !== '') {
                $dimensions[$key] = mb_substr($value, 0, 512);
            }
        }

        $granularity = in_array($request->query('interval'), self::GRANULARITIES, true) ? (string) $request->query('interval') : null;

        return new self($from->startOfDay(), $to->endOfDay(), $period, $compare, $dimensions, $granularity);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function customRange(Request $request, Carbon $today): array
    {
        $timezone = config('app.display_timezone');

        try {
            $from = Carbon::createFromFormat('Y-m-d', (string) $request->query('from'), $timezone)->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', (string) $request->query('to'), $timezone)->startOfDay();
        } catch (\Throwable) {
            return [$today->copy()->subDays(29), $today->copy()];
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        // Raw data is only kept for the retention window anyway.
        $earliest = $today->copy()->subDays(config('web-analytics.retention_days'));

        return [$from->max($earliest), $to->min($today)];
    }

    public function fromUtc(): Carbon
    {
        return $this->from->copy()->utc();
    }

    public function toUtc(): Carbon
    {
        return $this->to->copy()->utc();
    }

    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    public function includesToday(): bool
    {
        return $this->to->gte(now(config('app.display_timezone'))->startOfDay());
    }

    public function hasFilters(): bool
    {
        return $this->dimensions !== [];
    }

    public function filter(string $key): ?string
    {
        return $this->dimensions[$key] ?? null;
    }

    public function granularity(): string
    {
        if ($this->requestedGranularity !== null) {
            return $this->requestedGranularity;
        }

        return match (true) {
            $this->days() <= 2 => 'hour',
            $this->days() <= 92 => 'day',
            $this->days() <= 366 => 'week',
            default => 'month',
        };
    }

    /**
     * The same filters over the comparison range, or null when comparison
     * is switched off.
     */
    public function comparison(): ?self
    {
        return match ($this->compare) {
            'none' => null,
            'year' => new self(
                $this->from->copy()->subYearNoOverflow(),
                $this->to->copy()->subYearNoOverflow(),
                $this->period,
                'none',
                $this->dimensions,
                $this->requestedGranularity,
            ),
            default => new self(
                $this->from->copy()->subDays($this->days()),
                $this->to->copy()->subDays($this->days()),
                $this->period,
                'none',
                $this->dimensions,
                $this->requestedGranularity,
            ),
        };
    }

    /**
     * The same period and comparison, with a different set of dimensions.
     *
     * @param  array<string, string>  $dimensions
     */
    public function withDimensions(array $dimensions): self
    {
        return new self($this->from, $this->to, $this->period, $this->compare, $dimensions, $this->requestedGranularity);
    }

    public function cacheKey(string $prefix): string
    {
        return 'web-analytics:'.$prefix.':'.sha1(json_encode([
            $this->from->toIso8601String(),
            $this->to->toIso8601String(),
            $this->dimensions,
            $this->granularity(),
        ]));
    }

    /**
     * The query string describing these filters — every dashboard link
     * carries it so navigating keeps the current view.
     *
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return array_filter([
            'period' => $this->period,
            'from' => $this->period === 'custom' ? $this->from->toDateString() : null,
            'to' => $this->period === 'custom' ? $this->to->toDateString() : null,
            'compare' => $this->compare !== 'previous' ? $this->compare : null,
            'interval' => $this->requestedGranularity,
            'f' => $this->dimensions !== [] ? $this->dimensions : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * Query string with one more dimension filter — a drill-down link.
     *
     * @return array<string, mixed>
     */
    public function queryWith(string $dimension, string $value): array
    {
        return [...$this->query(), 'f' => [...$this->dimensions, $dimension => $value]];
    }

    /**
     * Query string with one dimension filter removed — a filter chip's ×.
     *
     * @return array<string, mixed>
     */
    public function queryWithout(string $dimension): array
    {
        $dimensions = $this->dimensions;
        unset($dimensions[$dimension]);

        return array_filter([...$this->query(), 'f' => $dimensions !== [] ? $dimensions : null], fn (mixed $value): bool => $value !== null);
    }

    public function label(): string
    {
        if ($this->period !== 'custom') {
            return self::PERIODS[$this->period];
        }

        return $this->from->format('d M Y').' – '.$this->to->format('d M Y');
    }
}
