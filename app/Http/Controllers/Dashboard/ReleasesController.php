<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\PublishReleaseRequest;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReleasesController extends Controller
{
    public function index(): View
    {
        return view('dashboard.releases', [
            'current' => Release::current(),
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
}
