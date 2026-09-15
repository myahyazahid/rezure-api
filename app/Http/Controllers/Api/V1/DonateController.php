<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DonateConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     */
    public function __invoke(Request $request): JsonResponse
    {
        $config = DonateConfig::current();

        $response = response()->json([
            'message' => $config->message,
            'local' => $config->local,
            'global' => $config->global,
            'crypto' => $config->crypto,
        ])->setLastModified($config->updated_at);

        // Mutates $response into a bodyless 304 in place when the caller's
        // If-Modified-Since is still current; a no-op otherwise.
        $response->isNotModified($request);

        return $response;
    }
}
