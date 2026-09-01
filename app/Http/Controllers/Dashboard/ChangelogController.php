<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ChangelogRequest;
use App\Models\Changelog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function index(Request $request): View
    {
        $editing = $request->filled('edit') ? Changelog::findOrFail($request->query('edit')) : null;

        return view('dashboard.changelog', [
            'changelogs' => Changelog::query()->orderByDesc('released_at')->paginate(15)->withQueryString(),
            'editing' => $editing,
        ]);
    }

    public function store(ChangelogRequest $request): RedirectResponse
    {
        Changelog::create($request->validated());

        return redirect()->route('dashboard.changelog')->with('status', 'Changelog entry created.');
    }

    public function update(ChangelogRequest $request, Changelog $changelog): RedirectResponse
    {
        $changelog->update($request->validated());

        return redirect()->route('dashboard.changelog')->with('status', 'Changelog entry updated.');
    }

    public function destroy(Changelog $changelog): RedirectResponse
    {
        $changelog->delete();

        return redirect()->route('dashboard.changelog')->with('status', 'Changelog entry deleted.');
    }
}
