<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Models\UpgradeNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UpgradeNoticeController extends Controller
{
    /**
     * `rezureapp`'s "a newer major is out" banner on the Changelog page (see
     * ../../../api-documentation/telemetry-api.md). It can't ride on
     * `/version/latest`: that answers `204` to a client already on the
     * newest release of its own line — exactly the client that most needs
     * to hear a new major exists.
     *
     * Always a link to the website, never an installer: moving to a new
     * major is the user's decision, not an auto-update.
     */
    public function __invoke(Request $request): JsonResponse|Response
    {
        $notice = UpgradeNotice::current();

        $requestedVersion = $request->query('current_version');
        $clientMajor = is_string($requestedVersion) ? Release::majorOf($requestedVersion) : null;

        if (! $notice->isShownTo($clientMajor)) {
            return response()->noContent();
        }

        return response()->json([
            'major' => $notice->major,
            'message' => $notice->message,
            'url' => $notice->url,
        ]);
    }
}
