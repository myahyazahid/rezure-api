<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sticker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class StickerController extends Controller
{
    /**
     * The catalog behind the desktop client's Decorations → Browse page:
     * every published sticker, newest first, in one response.
     *
     * Conditional GET via `ETag`/`If-None-Match`, for the same reason
     * `DonateController` uses `Last-Modified`: the client keeps the catalog
     * it fetched last, so a re-fetch after nothing changed is a bodyless
     * `304`. An ETag rather than a timestamp because deleting or unpublishing
     * a sticker must change it too, and a "latest `updated_at`" can't see a
     * row that is gone.
     *
     * `url` is where the file is served, for clients that want it; the
     * desktop client derives that address from `id` itself and never follows
     * one a response hands it.
     */
    public function index(Request $request): JsonResponse
    {
        $stickers = Sticker::query()->published()->latest('id')->get();

        $response = response()->json([
            'stickers' => $stickers->map(fn (Sticker $sticker): array => [
                'id' => $sticker->slug,
                'name' => $sticker->name,
                'category' => $sticker->category,
                'format' => $sticker->format,
                'size' => $sticker->size,
                'sha256' => $sticker->sha256,
                'url' => route('api.v1.stickers.file', $sticker->slug),
            ])->values()->all(),
        ]);

        $response->setEtag(sha1((string) $response->getContent()));
        $response->isNotModified($request);

        return $response;
    }

    /**
     * The image itself, streamed from the private disk rather than linked
     * through `/storage`, so the headers are ours:
     *
     * - `nosniff` and an explicit `Content-Type`, so a file is only ever
     *   treated as the format it was verified to be.
     * - A `sandbox` CSP, so an SVG opened directly in a browser can't run
     *   anything even if one slipped past upload validation. (In an `<img>` —
     *   how the client shows it — scripts never run in the first place.)
     * - The stored `sha256` as the `ETag`: the content hash is exactly what
     *   makes a cached copy valid.
     *
     * An unpublished sticker is a `404` here, same as a missing one.
     */
    public function file(Request $request, string $slug): Response
    {
        $sticker = Sticker::query()->published()->where('slug', $slug)->firstOrFail();

        $disk = Storage::disk('local');
        abort_unless($disk->exists($sticker->file_path), 404);

        $response = $disk->response($sticker->file_path, null, [
            'Content-Type' => $sticker->mimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'public, max-age=86400',
        ]);
        $response->setEtag($sticker->sha256);
        $response->isNotModified($request);

        return $response;
    }
}
