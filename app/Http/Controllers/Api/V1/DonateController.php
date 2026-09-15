<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DonateConfig;
use Illuminate\Http\JsonResponse;

class DonateController extends Controller
{
    /**
     * `local`/`global`/`crypto` may each be empty — the client renders
     * every section conditionally and shows "nothing configured" only if
     * all three are, so there's nothing special to do here when the
     * maintainer hasn't set anything up yet.
     */
    public function __invoke(): JsonResponse
    {
        $config = DonateConfig::current();

        return response()->json([
            'message' => $config->message,
            'local' => $config->local,
            'global' => $config->global,
            'crypto' => $config->crypto,
        ]);
    }
}
