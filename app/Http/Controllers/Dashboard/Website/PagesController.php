<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\ContentReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PagesController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TABS = ['pageviews' => 'All pages', 'entries' => 'Entry pages', 'exits' => 'Exit pages'];

    public function __construct(
        private readonly WebAnalyticsQuery $query,
        private readonly ContentReport $content,
    ) {}

    public function index(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'pageviews';
        $search = trim((string) $request->query('q')) ?: null;

        return view('dashboard.website.pages', [
            'filters' => $filters,
            'tabs' => self::TABS,
            'tab' => $tab,
            'search' => $search,
            'pages' => $this->query->remember($filters, "pages:{$tab}:{$search}", fn (): array => $this->query->pages($filters, $tab, 100, $search)),
        ]);
    }

    public function show(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $path = '/'.ltrim((string) $request->query('path', '/'), '/');

        return view('dashboard.website.page-detail', [
            'filters' => $filters,
            'detail' => $this->content->page($filters, mb_substr($path, 0, 512)),
        ]);
    }
}
