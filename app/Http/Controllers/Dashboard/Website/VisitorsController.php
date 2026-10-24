<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Models\WebVisitor;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitorsController extends Controller
{
    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * Visitors with at least one session in the period that matches the
     * filters, most recently seen first. The search box matches an IP, a
     * city, or the start of a visitor id.
     */
    public function index(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $search = trim((string) $request->query('q'));

        $visitors = WebVisitor::query()
            ->whereIn('id', $this->query->sessions($filters)->select('web_visitor_id'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('last_ip_address', 'like', $search.'%')
                ->orWhere('last_city', 'like', $search.'%')
                ->orWhere('visitor_uuid', 'like', str_replace('vis_', '', $search).'%')))
            ->with('device:id,device_name,device_id')
            ->latest('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.website.visitors', [
            'filters' => $filters,
            'visitors' => $visitors,
            'search' => $search,
        ]);
    }

    /**
     * Everything one visitor did: every session with its pageviews and
     * events, newest first.
     */
    public function show(WebVisitor $webVisitor): View
    {
        $webVisitor->load([
            'device:id,device_id,device_name,first_seen_at,last_seen_at',
            'sessions' => fn ($query) => $query->latest('started_at')->limit(50),
            'sessions.pageviews' => fn ($query) => $query->orderBy('sequence'),
            'sessions.events' => fn ($query) => $query->orderBy('occurred_at'),
        ]);

        return view('dashboard.website.visitor-detail', ['visitor' => $webVisitor]);
    }
}
