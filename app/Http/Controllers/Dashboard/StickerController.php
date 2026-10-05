<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StickerRequest;
use App\Models\Sticker;
use App\Support\StickerFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StickerController extends Controller
{
    public function index(): View
    {
        return view('dashboard.stickers.index', [
            'stickers' => Sticker::query()->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.stickers.form', [
            'sticker' => null,
            'categories' => $this->knownCategories(),
        ]);
    }

    public function store(StickerRequest $request): RedirectResponse
    {
        $slug = $this->uniqueSlug($request->string('name')->toString());

        Sticker::create([
            'slug' => $slug,
            'name' => $request->string('name')->toString(),
            'category' => $request->string('category')->toString(),
            'is_published' => $request->boolean('is_published'),
            ...$this->storeFile($request->file('file'), $slug),
        ]);

        return redirect()->route('dashboard.stickers.index')->with('status', 'Sticker added.');
    }

    public function edit(Sticker $sticker): View
    {
        return view('dashboard.stickers.form', [
            'sticker' => $sticker,
            'categories' => $this->knownCategories(),
        ]);
    }

    /**
     * The slug never changes: it is the id installed clients saved the
     * sticker under, so renaming one only changes the name people see.
     * Replacing the image changes its hash, which is how the client knows
     * its downloaded copy is out of date.
     */
    public function update(StickerRequest $request, Sticker $sticker): RedirectResponse
    {
        $attributes = [
            'name' => $request->string('name')->toString(),
            'category' => $request->string('category')->toString(),
            'is_published' => $request->boolean('is_published'),
        ];

        $previousPath = $sticker->file_path;
        $file = $request->file('file');

        if ($file instanceof UploadedFile) {
            $attributes += $this->storeFile($file, $sticker->slug);
        }

        $sticker->update($attributes);

        // A new format means a new path (`x.svg` → `x.png`); the old file
        // would otherwise be left behind with nothing pointing at it.
        if ($file instanceof UploadedFile && $previousPath !== $sticker->file_path) {
            Storage::disk('local')->delete($previousPath);
        }

        return redirect()->route('dashboard.stickers.index')->with('status', 'Sticker updated.');
    }

    /**
     * The image for the dashboard's own thumbnails. The public file route
     * answers `404` for an unpublished sticker, which would leave a draft
     * showing a broken image to the one person who needs to see it.
     */
    public function preview(Sticker $sticker): Response
    {
        abort_unless(Storage::disk('local')->exists($sticker->file_path), 404);

        return Storage::disk('local')->response($sticker->file_path, null, [
            'Content-Type' => $sticker->mimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            // Replacing the image keeps the id, so a long cache would show
            // the old one in the list after an edit.
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function togglePublish(Sticker $sticker): RedirectResponse
    {
        $sticker->update(['is_published' => ! $sticker->is_published]);

        return redirect()->route('dashboard.stickers.index')
            ->with('status', $sticker->is_published ? 'Sticker published.' : 'Sticker hidden from the catalog.');
    }

    public function destroy(Sticker $sticker): RedirectResponse
    {
        Storage::disk('local')->delete($sticker->file_path);
        $sticker->delete();

        return redirect()->route('dashboard.stickers.index')->with('status', 'Sticker deleted.');
    }

    /**
     * Writes the upload to the private disk and returns the columns that
     * describe it. The format comes from the file's bytes, and the name on
     * disk from the slug — nothing of what the browser called the file.
     *
     * @return array{format: string, file_path: string, size: int, sha256: string}
     */
    private function storeFile(UploadedFile $file, string $slug): array
    {
        $contents = (string) $file->get();
        $format = StickerFile::detectFormat($contents);
        $path = "stickers/{$slug}.{$format}";

        Storage::disk('local')->put($path, $contents);

        return [
            'format' => $format,
            'file_path' => $path,
            'size' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ];
    }

    /**
     * `pink-bow`, then `pink-bow-2`, `pink-bow-3`… Capped well under the
     * column's 64 characters so the suffix always fits.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 56, '') ?: 'sticker';
        $slug = $base;

        for ($suffix = 2; Sticker::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    /**
     * Categories already in use plus the two the client ships with, for the
     * form's suggestions — free text still works for a new one.
     *
     * @return list<string>
     */
    private function knownCategories(): array
    {
        return Sticker::query()->distinct()->pluck('category')
            ->merge(['girls', 'mens', 'general'])
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
