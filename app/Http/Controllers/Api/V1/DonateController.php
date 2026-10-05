<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DonateConfig;
use App\Models\DonateMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DonateController extends Controller
{
    /**
     * `local`/`global`/`crypto` may each be empty — the client renders
     * every section conditionally and shows "nothing configured" only if
     * all three are, so there's nothing special to do here when the
     * maintainer hasn't set anything up yet.
     *
     * Conditional GET via `Last-Modified`/`If-Modified-Since`: the client
     * caches this response and sends back the `Last-Modified` it was given,
     * so a re-fetch after nothing changed costs a bodyless `304` instead of
     * the full payload — the Support Developer page stays populated from
     * its own cache without needing this endpoint reachable on every app
     * open, it just refreshes opportunistically when it is.
     *
     * `DonateConfig::current()->updated_at` is the single source for that
     * header — every create/update/delete of a `DonateMethod` also touches
     * that row (see `Dashboard\DonateController`) precisely so this stays
     * accurate without having to aggregate across the methods table (which
     * would go stale the moment the most-recently-touched row is deleted).
     */
    public function __invoke(Request $request): JsonResponse
    {
        $config = DonateConfig::current();
        $methods = DonateMethod::query()->orderBy('id')->get();

        $response = response()->json([
            'message' => $config->message,
            'local' => $this->links($methods, 'local'),
            'global' => $this->links($methods, 'global'),
            'crypto' => $this->wallets($methods),
            'qris' => $this->qrisDescriptor($config),
        ])->setLastModified($config->updated_at);

        // Mutates $response into a bodyless 304 in place when the caller's
        // If-Modified-Since is still current; a no-op otherwise.
        $response->isNotModified($request);

        return $response;
    }

    /**
     * The optional QRIS image, streamed from the private disk so the headers
     * are ours. `no-cache` plus the content hash as `ETag`: a client may keep
     * a copy, but must revalidate it every time — a stale QRIS would send a
     * donation to an account the maintainer already replaced.
     *
     * `404` when none has been uploaded, same as a missing file.
     */
    public function qrisFile(Request $request): Response
    {
        $config = DonateConfig::current();
        abort_unless($config->hasQris(), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($config->qris_path), 404);

        $response = $disk->response($config->qris_path, null, [
            'Content-Type' => $config->qrisMimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-cache',
        ]);
        $response->setEtag($config->qris_sha256);
        $response->isNotModified($request);

        return $response;
    }

    /**
     * `null` until a maintainer uploads one — clients show the QRIS section
     * only when this is present. `url` is where the image is served; `sha256`
     * is what a client compares to know its saved copy is out of date.
     *
     * @return array{format: string, size: int, sha256: string, url: string}|null
     */
    private function qrisDescriptor(DonateConfig $config): ?array
    {
        if (! $config->hasQris()) {
            return null;
        }

        return [
            'format' => $config->qris_format,
            'size' => $config->qris_size,
            'sha256' => $config->qris_sha256,
            'url' => route('api.v1.support.donate.qris'),
        ];
    }

    /**
     * `id` is what a client builds an icon's address from — it doesn't
     * follow `icon.url`, same as it doesn't for the QRIS and stickers.
     * `icon` is `null` for a link without one — a client shows it without a
     * logo then.
     *
     * @return list<array{id: int, label: string, url: ?string, icon: array{format: string, size: int, sha256: string, url: string}|null}>
     */
    private function links(Collection $methods, string $category): array
    {
        return $methods->where('category', $category)
            ->map(fn (DonateMethod $method): array => [
                'id' => $method->id,
                'label' => $method->label,
                'url' => $method->url,
                'icon' => $this->iconDescriptor($method),
            ])
            ->values()
            ->all();
    }

    /**
     * `id` and `icon` work as they do for links. `network` is the chain the
     * address is on; it is `null` only for a wallet saved before the field
     * existed (the dashboard requires it for every new or edited one).
     *
     * @return list<array{id: int, symbol: ?string, network: ?string, label: string, address: ?string, icon: array{format: string, size: int, sha256: string, url: string}|null}>
     */
    private function wallets(Collection $methods): array
    {
        return $methods->where('category', 'crypto')
            ->map(fn (DonateMethod $method): array => [
                'id' => $method->id,
                'symbol' => $method->symbol,
                'network' => $method->network,
                'label' => $method->label,
                'address' => $method->address,
                'icon' => $this->iconDescriptor($method),
            ])
            ->values()
            ->all();
    }

    /**
     * `sha256` is what a client compares to know its saved copy of the icon
     * is out of date; `url` is where the image is served.
     *
     * @return array{format: string, size: int, sha256: string, url: string}|null
     */
    private function iconDescriptor(DonateMethod $method): ?array
    {
        if (! $method->hasIcon()) {
            return null;
        }

        return [
            'format' => $method->icon_format,
            'size' => $method->icon_size,
            'sha256' => $method->icon_sha256,
            'url' => route('api.v1.support.donate.icon', $method),
        ];
    }

    /**
     * A donate method's icon (any category), streamed from the private disk
     * so the headers are ours: `nosniff`, an explicit `Content-Type`, and a
     * `sandbox` CSP so an SVG opened directly in a browser can't run
     * anything. The stored `sha256` is the `ETag` — the content hash is
     * exactly what makes a cached copy valid — so a long `max-age` is safe.
     *
     * `404` for a method that has no icon, doesn't exist, or whose file is
     * gone.
     */
    public function iconFile(Request $request, DonateMethod $donateMethod): Response
    {
        abort_unless($donateMethod->hasIcon(), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($donateMethod->icon_path), 404);

        $response = $disk->response($donateMethod->icon_path, null, [
            'Content-Type' => $donateMethod->iconMimeType(),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'public, max-age=86400',
        ]);
        $response->setEtag($donateMethod->icon_sha256);
        $response->isNotModified($request);

        return $response;
    }
}
