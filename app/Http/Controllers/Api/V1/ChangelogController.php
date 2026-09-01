<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Changelog;
use Illuminate\Http\JsonResponse;

class ChangelogController extends Controller
{
    /**
     * Capped at 20 so a client's changelog feed never grows unbounded —
     * nothing calls for pagination on a public, non-sensitive read.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json(
            Changelog::query()
                ->orderByDesc('released_at')
                ->limit(20)
                ->get(['version', 'title', 'body', 'released_at'])
        );
    }
}
