<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\UpdatePortProfileRequest;
use App\Modules\MasterData\Http\Resources\PortProfileResource;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\MasterData\Services\PortProfileService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;

/**
 * The port profile (P2-F1, FR-MD-01, P-26). The read carries `ability:port-state.read`, the update `ability:port-setup.manage`.
 * There is one row; the update path carries its id so that `version.token` checks it like every other record.
 */
class PortProfileController extends Controller
{
    public function __construct(private readonly PortProfileService $profiles) {}

    /**
     * GET /api/v1/port-profile: 200; 401, 403, 503 while the port is not provisioned (no row, as GET /system/info).
     *
     * @throws ApiProblemException 503 SERVICE_UNAVAILABLE.
     */
    public function show(): PortProfileResource
    {
        $profile = PortProfile::query()->find(1) ?? throw new ApiProblemException(ErrorCode::ServiceUnavailable);

        return new PortProfileResource($profile);
    }

    /**
     * PUT /api/v1/port-profile/{portProfile}: the Port Admin's fields; 404, 409 VERSION_CONFLICT, 422.
     */
    public function update(UpdatePortProfileRequest $request, PortProfile $portProfile): PortProfileResource
    {
        $data = $request->safe()->except('version');

        return new PortProfileResource($this->profiles->update($portProfile, $data, $request->validated('version'), $request->user()->id));
    }
}
