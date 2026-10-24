<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Models\WebSession;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function __invoke(WebSession $webSession): View
    {
        $webSession->load([
            'visitor',
            'pageviews' => fn ($query) => $query->orderBy('sequence'),
            'events' => fn ($query) => $query->orderBy('occurred_at'),
            'errors' => fn ($query) => $query->orderBy('occurred_at'),
        ])->loadCount(['clicks', 'clicks as rage_clicks_count' => fn ($query) => $query->where('is_rage', true)]);

        return view('dashboard.website.session-detail', ['session' => $webSession]);
    }
}
