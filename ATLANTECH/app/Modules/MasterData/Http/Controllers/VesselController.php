<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\UpdateVesselRequest;
use App\Modules\MasterData\Http\Resources\VesselResource;
use App\Modules\MasterData\Models\Vessel;
use App\Modules\MasterData\Services\VesselService;

/**
 * A vessel's particulars for P-33 (P3-H7, FR-BQ-13). The read carries `ability:port-state.read`, the edit
 * `ability:baplie.manage` and `version.token:vessel`. A vessel belongs to no terminal, so there is no terminal scope.
 */
class VesselController extends Controller
{
    public function __construct(private readonly VesselService $vessels) {}

    /**
     * GET /api/v1/vessels/{vessel}: 401, 403, 404.
     */
    public function show(Vessel $vessel): VesselResource
    {
        return new VesselResource($vessel->load('vesselType'));
    }

    /**
     * PUT /api/v1/vessels/{vessel}: 401, 403, 404, 409 VERSION_CONFLICT, 422.
     */
    public function update(UpdateVesselRequest $request, Vessel $vessel): VesselResource
    {
        $updated = $this->vessels->update(
            $vessel,
            $request->safe()->except('version'),
            $request->validated('version'),
            $request->user()->id,
        );

        return new VesselResource($updated->load('vesselType'));
    }
}
