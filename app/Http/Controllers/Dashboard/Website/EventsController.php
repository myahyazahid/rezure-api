<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\ConversionReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventsController extends Controller
{
    public function __construct(private readonly ConversionReport $report) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $events = $this->report->events($filters);
        $selected = $request->query('event');
        $selected = is_string($selected) && in_array($selected, array_column($events, 'name'), true) ? $selected : ($events[0]['name'] ?? null);

        return view('dashboard.website.events', [
            'filters' => $filters,
            'events' => $events,
            'selected' => $selected,
            'detail' => $selected !== null ? $this->report->eventDetail($filters, $selected) : null,
        ]);
    }
}
