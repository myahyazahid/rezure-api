<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DonateConfig;
use App\Models\DonateMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
        ])->setLastModified($config->updated_at);

        // Mutates $response into a bodyless 304 in place when the caller's
        // If-Modified-Since is still current; a no-op otherwise.
        $response->isNotModified($request);

        return $response;
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
