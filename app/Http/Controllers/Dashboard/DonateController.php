<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DonateMethodRequest;
use App\Http\Requests\Dashboard\UpdateDonateMessageRequest;
use App\Models\DonateConfig;
use App\Models\DonateMethod;
use App\Support\DonatePresets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DonateController extends Controller
{
    public function index(): View
    {
        $methods = DonateMethod::query()->orderBy('id')->get()->groupBy('category');

        return view('dashboard.donate.index', [
            'config' => DonateConfig::current(),
            'local' => $methods->get('local', collect()),
            'global' => $methods->get('global', collect()),
            'crypto' => $methods->get('crypto', collect()),
        ]);
    }

    public function updateMessage(UpdateDonateMessageRequest $request): RedirectResponse
    {
        DonateConfig::current()->update($request->validated());

        return redirect()->route('dashboard.donate')->with('status', 'Message updated.');
    }

    /**
     * With no `preset` chosen yet, the view shows the picker (known
     * platforms for this category, plus "Custom"); once one's picked it's
     * back here as a query param and the view shows the actual fields.
     */
    public function create(Request $request): View
    {
        $category = $request->query('category', 'local');
        abort_unless(in_array($category, DonatePresets::categories(), true), 404);

        $preset = $request->query('preset');

        return view('dashboard.donate.form', [
            'method' => null,
            'category' => $category,
            'preset' => $preset,
            'presets' => DonatePresets::forCategory($category),
        ]);
    }

    public function store(DonateMethodRequest $request): RedirectResponse
    {
        DonateMethod::create($request->validated());
        DonateConfig::current()->touch();

        return redirect()->route('dashboard.donate')->with('status', 'Donate method added.');
    }

    public function edit(DonateMethod $donateMethod): View
    {
        return view('dashboard.donate.form', [
            'method' => $donateMethod,
            'category' => $donateMethod->category,
            'preset' => $donateMethod->preset,
            'presets' => DonatePresets::forCategory($donateMethod->category),
        ]);
    }

    public function update(DonateMethodRequest $request, DonateMethod $donateMethod): RedirectResponse
    {
        $donateMethod->update($request->validated());
        DonateConfig::current()->touch();

        return redirect()->route('dashboard.donate')->with('status', 'Donate method updated.');
    }

    public function destroy(DonateMethod $donateMethod): RedirectResponse
    {
        $donateMethod->delete();
        DonateConfig::current()->touch();

        return redirect()->route('dashboard.donate')->with('status', 'Donate method removed.');
    }
}
