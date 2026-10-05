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
     * @return list<array{label: string, url: ?string}>
     */
    private function links(Collection $methods, string $category): array
    {
        return $methods->where('category', $category)
            ->map(fn (DonateMethod $method): array => ['label' => $method->label, 'url' => $method->url])
            ->values()
            ->all();
    }

    /**
     * @return list<array{symbol: ?string, label: string, address: ?string}>
     */
    private function wallets(Collection $methods): array
    {
        return $methods->where('category', 'crypto')
            ->map(fn (DonateMethod $method): array => [
                'symbol' => $method->symbol,
                'label' => $method->label,
                'address' => $method->address,
            ])
            ->values()
            ->all();
    }
}
