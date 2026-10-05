<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DonateMethodRequest;
use App\Http\Requests\Dashboard\UpdateDonateMessageRequest;
use App\Http\Requests\Dashboard\UpdateDonateQrisRequest;
use App\Models\DonateConfig;
use App\Models\DonateMethod;
use App\Support\DonatePresets;
use App\Support\QrisFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
     * Upload or replace the optional QRIS image. The format comes from the
     * file's bytes and the name on disk is fixed, so nothing of what the
     * browser called the file is used. Saving touches `updated_at` like any
     * other donate change, which is what lets cached clients notice it.
     */
    public function updateQris(UpdateDonateQrisRequest $request): RedirectResponse
    {
        $config = DonateConfig::current();
        $previousPath = $config->qris_path;

        $contents = (string) $request->file('qris')->get();
        $format = QrisFile::detectFormat($contents);
        $path = "donate/qris.{$format}";

        Storage::disk('local')->put($path, $contents);

        $config->update([
            'qris_path' => $path,
            'qris_format' => $format,
            'qris_size' => strlen($contents),
            'qris_sha256' => hash('sha256', $contents),
        ]);

        // A new format means a new path (`qris.png` → `qris.jpg`); the old
        // file would otherwise be left behind with nothing pointing at it.
        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return redirect()->route('dashboard.donate')->with('status', 'QRIS saved.');
    }

    public function destroyQris(): RedirectResponse
    {
        $config = DonateConfig::current();

        if ($config->hasQris()) {
            Storage::disk('local')->delete($config->qris_path);
            $config->update([
                'qris_path' => null,
                'qris_format' => null,
                'qris_size' => null,
                'qris_sha256' => null,
            ]);
        }

        return redirect()->route('dashboard.donate')->with('status', 'QRIS removed.');
    }

    /**
     * The image for the dashboard's own preview. `no-cache` so a replaced
     * QRIS shows up straight away instead of the old one.
     */
    public function previewQris(): Response
    {
        $config = DonateConfig::current();
        abort_unless($config->hasQris() && Storage::disk('local')->exists($config->qris_path), 404);

        return Storage::disk('local')->response($config->qris_path, null, [
            'Content-Type' => $config->qrisMimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache',
        ]);
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
