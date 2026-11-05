<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\License\Enums\LicenseMode;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\MinAppVersionSource;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/system/info (CT-04 v1.1, P1-C5): public, never refused with 426, and the app's connectivity check (DEC-58).
 */
class SystemInfoController extends Controller
{
    /**
     * 200 with the port and the minimum app version; 503 SERVICE_UNAVAILABLE while the port is not provisioned (no profile row or setting).
     *
     * @throws ApiProblemException 503 SERVICE_UNAVAILABLE.
     */
    public function __invoke(MinAppVersionSource $versions): JsonResponse
    {
        $port = PortProfile::query()->find(1);

        if ($port === null) {
            throw new ApiProblemException(ErrorCode::ServiceUnavailable);
        }

        return response()->json([
            'port_id' => $port->public_id,
            'port_code' => $port->port_code,
            'port_name' => $port->name,
            // Fixed until the licence work (DEC-54, lead 2026-10-31).
            'license_mode' => LicenseMode::Full->value,
            'token_warning_days_left' => null,
            'min_app_version' => $versions->minAppVersion(),
        ]);
    }
}
