<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Release;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    /**
     * "No release published yet" is a normal, expected state — not an
     * error — so this returns 200 with null fields rather than a 404.
     */
    public function __invoke(): JsonResponse
    {
        $release = Release::current();

        return response()->json([
            'version' => $release?->version,
            'notes' => $release?->notes,
            'published_at' => $release?->published_at?->toIso8601String(),
        ]);
    }
}
