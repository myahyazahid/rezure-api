<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\RecordSiteDownloadJob;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteDownloadController extends Controller
{
    /**
     * The website's download button points here: the download is recorded
     * on the queue and the visitor is sent on to the installer. The tracker
     * ids are optional and read leniently — this is a navigation, so a bad
     * id must never turn into a 422 page instead of the installer.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $release = Release::current();

        RecordSiteDownloadJob::dispatch([
            'event_uuid' => (string) Str::uuid(),
            'visitor_uuid' => $this->uuidOrNull($request->query('vid')),
            'session_uuid' => $this->uuidOrNull($request->query('sid')),
            'pageview_uuid' => $this->uuidOrNull($request->query('pid')),
            'version' => $release?->version,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'occurred_at' => now()->toIso8601String(),
        ]);

        return redirect()->away($release?->download_url ?: config('web-analytics.download_fallback_url'));
    }

    private function uuidOrNull(mixed $value): ?string
    {
        return is_string($value) && Str::isUuid($value) ? $value : null;
    }
}
