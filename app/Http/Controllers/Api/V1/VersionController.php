<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VersionController extends Controller
{
    /**
     * This is Tauri's own updater-manifest shape (see
     * ../../../api-documentation/telemetry-api.md), not a plain version
     * string — `tauri-plugin-updater` verifies `platforms.windows-x86_64`'s
     * `signature` against a public key baked into the client, independent
     * of anything this response claims.
     *
     * "Nothing to update to" is `204`, not `200` with null/equal fields —
     * the plugin's `check()` treats the two differently. That covers both
     * "no release has ever been published" and, when the requesting
     * client's version rides along as `?current_version=`, "you're already
     * current". `current_version` is optional: a caller that just wants to
     * read the latest version for display (no update semantics) gets a
     * `200` either way.
     */
    public function __invoke(Request $request): JsonResponse|Response
    {
        $release = Release::current();

        if (! $release) {
            return response()->noContent();
        }

        $requestedVersion = $request->query('current_version');

        if (is_string($requestedVersion) && version_compare($release->version, $requestedVersion, '<=')) {
            return response()->noContent();
        }

        return response()->json([
            'version' => $release->version,
            'notes' => $release->notes,
            'pub_date' => $release->published_at?->toIso8601String(),
            'platforms' => $release->signature && $release->download_url
                ? ['windows-x86_64' => ['signature' => $release->signature, 'url' => $release->download_url]]
                : [],
        ]);
    }
}
