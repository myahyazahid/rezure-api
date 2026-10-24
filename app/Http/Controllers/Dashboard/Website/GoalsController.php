<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\WebGoalRequest;
use App\Models\WebGoal;
use App\Services\WebAnalytics\Reports\ConversionReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebDimensions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoalsController extends Controller
{
    /**
     * Dimensions a goal's conversion rate can be broken down by.
     *
     * @var list<string>
     */
    private const BREAKDOWNS = ['channel', 'source', 'entry_page', 'country', 'device_type', 'browser', 'visitor_type'];

    public function __construct(private readonly ConversionReport $report) {}

    public function index(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $goals = $this->report->goals($filters);
        $selected = WebGoal::find((int) $request->query('goal')) ?? ($goals[0]['goal'] ?? null);
        $by = in_array($request->query('by'), self::BREAKDOWNS, true) ? (string) $request->query('by') : 'channel';

        return view('dashboard.website.goals', [
            'filters' => $filters,
            'goals' => $goals,
            'selected' => $selected,
            'by' => $by,
            'breakdowns' => array_combine(self::BREAKDOWNS, array_map(WebDimensions::label(...), self::BREAKDOWNS)),
            'breakdown' => $selected !== null ? $this->report->goalBreakdown($filters, $selected, $by) : [],
            'editing' => WebGoal::find((int) $request->query('edit')),
        ]);
    }

    public function store(WebGoalRequest $request): RedirectResponse
    {
        WebGoal::create($request->validated());

        return redirect()->route('dashboard.website.goals')->with('status', 'Goal created.');
    }

    public function update(WebGoalRequest $request, WebGoal $webGoal): RedirectResponse
    {
        $webGoal->update(['is_active' => false, ...$request->validated()]);

        return redirect()->route('dashboard.website.goals')->with('status', 'Goal updated.');
    }

    public function destroy(WebGoal $webGoal): RedirectResponse
    {
        $webGoal->delete();

        return redirect()->route('dashboard.website.goals')->with('status', 'Goal deleted.');
    }
}
