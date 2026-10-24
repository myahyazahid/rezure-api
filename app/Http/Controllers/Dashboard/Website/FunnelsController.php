<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\WebFunnelRequest;
use App\Models\WebFunnel;
use App\Services\WebAnalytics\Reports\ConversionReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FunnelsController extends Controller
{
    /**
     * @var list<string>
     */
    private const BREAKDOWNS = ['channel', 'source', 'country', 'device_type', 'browser', 'visitor_type'];

    public function __construct(
        private readonly ConversionReport $report,
        private readonly WebAnalyticsQuery $query,
    ) {}

    public function index(Request $request): View
    {
        return view('dashboard.website.funnels', [
            'filters' => WebAnalyticsFilters::fromRequest($request),
            'funnels' => WebFunnel::query()->orderBy('name')->get(),
        ]);
    }

    public function store(WebFunnelRequest $request): RedirectResponse
    {
        $funnel = WebFunnel::create($request->validated());

        return redirect()->route('dashboard.website.funnels.show', $funnel)->with('status', 'Funnel saved.');
    }

    public function show(Request $request, WebFunnel $webFunnel): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $by = in_array($request->query('by'), self::BREAKDOWNS, true) ? (string) $request->query('by') : 'channel';

        return view('dashboard.website.funnel-detail', [
            'filters' => $filters,
            'funnel' => $webFunnel,
            'by' => $by,
            'breakdowns' => array_combine(self::BREAKDOWNS, array_map(WebDimensions::label(...), self::BREAKDOWNS)),
            'result' => $this->query->remember($filters, "funnel:{$webFunnel->id}:{$webFunnel->updated_at?->timestamp}:{$by}", fn (): array => $this->report->funnel(
                $filters,
                $webFunnel->steps,
                $webFunnel->scope,
                $webFunnel->window_hours,
                $by,
            )),
        ]);
    }

    public function destroy(WebFunnel $webFunnel): RedirectResponse
    {
        $webFunnel->delete();

        return redirect()->route('dashboard.website.funnels')->with('status', 'Funnel deleted.');
    }
}
