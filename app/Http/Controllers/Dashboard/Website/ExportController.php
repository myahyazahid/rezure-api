<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\ConversionReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use App\Support\WebDisplay;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export of any website table, with the same filters as the page it
 * was exported from: ?report=pages|events|breakdown&dimension=country.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly WebAnalyticsQuery $query,
        private readonly ConversionReport $conversions,
    ) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $report = (string) $request->query('report', 'pages');
        $dimension = (string) $request->query('dimension', 'channel');

        abort_unless(in_array($report, ['pages', 'events', 'breakdown'], true), 404);
        abort_if($report === 'breakdown' && ! WebDimensions::isSessionDimension($dimension), 404);

        [$header, $rows] = match ($report) {
            'events' => [
                ['event', 'total', 'visitors', 'sessions', 'per_session'],
                array_map(fn (array $row): array => [$row['name'], $row['total'], $row['visitors'], $row['sessions'], $row['per_session']], $this->conversions->events($filters)),
            ],
            'breakdown' => [
                [$dimension, 'visitors', 'sessions', 'pageviews', 'bounce_rate', 'avg_duration_seconds', 'downloads', 'conversion_rate'],
                array_map(fn (array $row): array => [
                    WebDisplay::value($dimension, $row['value']), $row['visitors'], $row['sessions'], $row['pageviews'],
                    $row['bounce_rate'], $row['avg_duration'], $row['conversions'], $row['conversion_rate'],
                ], $this->query->breakdown($filters, $dimension, 1000)),
            ],
            default => [
                ['path', 'pageviews', 'visitors', 'entries', 'exits', 'exit_rate', 'bounce_rate', 'avg_engaged_seconds', 'avg_scroll_pct', 'not_found'],
                array_map(fn (array $row): array => [
                    $row['path'], $row['pageviews'], $row['visitors'], $row['entries'], $row['exits'],
                    $row['exit_rate'], $row['bounce_rate'], $row['avg_engaged_seconds'], $row['avg_scroll'], $row['not_found'],
                ], $this->query->pages($filters, 'pageviews', 5000)),
            ],
        };

        $filename = sprintf('website-%s%s-%s-%s.csv', $report, $report === 'breakdown' ? '-'.$dimension : '', $filters->from->toDateString(), $filters->to->toDateString());

        return response()->streamDownload(function () use ($header, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                // Spreadsheet apps execute cells starting with = + - @ —
                // paths and referrers here are visitor-controlled.
                fputcsv($handle, array_map(fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
