<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PublishReleaseRequest;
use App\Http\Requests\Dashboard\UpdateUpgradeNoticeRequest;
use App\Models\Release;
use App\Models\UpgradeNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReleasesController extends Controller
{
    public function index(): View
    {
        return view('dashboard.releases', [
            'lines' => Release::currentPerLine(),
            'notice' => UpgradeNotice::current(),
            'releases' => Release::query()->orderByDesc('published_at')->paginate(15),
        ]);
    }

    public function store(PublishReleaseRequest $request): RedirectResponse
    {
        Release::create([
            ...$request->validated(),
            'published_at' => now(),
        ]);

        return redirect()->route('dashboard.releases')->with('status', 'Release published.');
    }

    /**
     * `enabled` is read with `boolean()` rather than taken from
     * `validated()` so an unchecked box (which the browser doesn't send at
     * all) always switches the notice off.
     */
    public function updateUpgradeNotice(UpdateUpgradeNoticeRequest $request): RedirectResponse
    {
        UpgradeNotice::current()->update([
            ...$request->validated(),
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()->route('dashboard.releases')->with('status', 'Upgrade notice saved.');
    }
}
