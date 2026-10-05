<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DonateMethodRequest;
use App\Http\Requests\Dashboard\UpdateDonateMessageRequest;
use App\Http\Requests\Dashboard\UpdateDonateQrisRequest;
use App\Models\DonateConfig;
use App\Models\DonateMethod;
use App\Support\DonatePresets;
use App\Support\IconFile;
use App\Support\QrisFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
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
        $method = DonateMethod::create($this->methodAttributes($request));

        if ($request->file('icon') instanceof UploadedFile) {
            $this->storeIcon($method, $request->file('icon'));
        }

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

    /**
     * Leaving the icon field empty keeps the current icon; a new file
     * replaces it, and `remove_icon` drops it. A new file wins over
     * `remove_icon` if both arrive.
     */
    public function update(DonateMethodRequest $request, DonateMethod $donateMethod): RedirectResponse
    {
        $donateMethod->update($this->methodAttributes($request));

        if ($request->file('icon') instanceof UploadedFile) {
            $this->storeIcon($donateMethod, $request->file('icon'));
        } elseif ($request->boolean('remove_icon')) {
            $this->removeIcon($donateMethod);
        }

        DonateConfig::current()->touch();

        return redirect()->route('dashboard.donate')->with('status', 'Donate method updated.');
    }

    public function destroy(DonateMethod $donateMethod): RedirectResponse
    {
        $this->removeIcon($donateMethod);
        $donateMethod->delete();
        DonateConfig::current()->touch();

        return redirect()->route('dashboard.donate')->with('status', 'Donate method removed.');
    }

    /**
     * The icon for the dashboard's own thumbnails. `no-cache` so a replaced
     * icon shows up straight away, and the same `sandbox` CSP the public
     * route sends, so an SVG opened directly can't run anything.
     */
    public function previewIcon(DonateMethod $donateMethod): Response
    {
        abort_unless($donateMethod->hasIcon() && Storage::disk('local')->exists($donateMethod->icon_path), 404);

        return Storage::disk('local')->response($donateMethod->icon_path, null, [
            'Content-Type' => $donateMethod->iconMimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * What goes into the row: the validated fields minus the two that
     * describe the icon upload, which `storeIcon()`/`removeIcon()` handle.
     *
     * @return array<string, mixed>
     */
    private function methodAttributes(DonateMethodRequest $request): array
    {
        return Arr::except($request->validated(), ['icon', 'remove_icon']);
    }

    /**
     * Writes the upload to the private disk and records what it is. The
     * format comes from the file's bytes and the name on disk from the
     * method's id — nothing of what the browser called the file.
     */
    private function storeIcon(DonateMethod $method, UploadedFile $file): void
    {
        $previousPath = $method->icon_path;

        $contents = (string) $file->get();
        $format = IconFile::detectFormat($contents);
        $path = "donate/icons/{$method->id}.{$format}";

        Storage::disk('local')->put($path, $contents);

        $method->update([
            'icon_path' => $path,
            'icon_format' => $format,
            'icon_size' => strlen($contents),
            'icon_sha256' => hash('sha256', $contents),
        ]);

        // A new format means a new path (`3.png` → `3.svg`); the old file
        // would otherwise be left behind with nothing pointing at it.
        if ($previousPath !== null && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    private function removeIcon(DonateMethod $method): void
    {
        if (! $method->hasIcon()) {
            return;
        }

        Storage::disk('local')->delete($method->icon_path);
        $method->update([
            'icon_path' => null,
            'icon_format' => null,
            'icon_size' => null,
            'icon_sha256' => null,
        ]);
    }
}
