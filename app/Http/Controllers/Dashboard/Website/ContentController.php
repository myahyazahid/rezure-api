<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\ContentReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function __construct(
        private readonly WebAnalyticsQuery $query,
        private readonly ContentReport $content,
    ) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);

        return view('dashboard.website.content', [
            'filters' => $filters,
            'blog' => $this->content->blog($filters),
            'docs' => $this->query->remember($filters, 'content-docs', fn (): array => $this->query->pages($filters, 'pageviews', 30, '/guide')),
            'searches' => $this->query->remember($filters, 'content-searches', fn (): array => $this->content->docsSearches($filters)),
            'notFound' => $this->query->remember($filters, 'content-404', fn (): array => $this->content->notFound($filters)),
        ]);
    }
}
