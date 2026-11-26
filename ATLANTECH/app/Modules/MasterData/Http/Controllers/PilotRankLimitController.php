<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\PageIndexRequest;
use App\Modules\MasterData\Http\Requests\UpdatePilotRankLimitRequest;
use App\Modules\MasterData\Http\Resources\PilotRankLimitCollection;
use App\Modules\MasterData\Http\Resources\PilotRankLimitResource;
use App\Modules\MasterData\Models\PilotRankLimit;
use App\Modules\MasterData\Services\PilotRankLimitService;

/**
 * Rank limits (P2-F3, FR-MD-09, P-43): read with `port-state.read`, changed by the Pilotage Manager (`pilot-roster.manage`, Q2).
 */
class PilotRankLimitController extends Controller
{
    public function __construct(private readonly PilotRankLimitService $limits) {}

    /**
     * GET /api/v1/pilot-rank-limits: the three ranks, most senior first; 401, 403, 422.
     */
    public function index(PageIndexRequest $request): PilotRankLimitCollection
    {
        return new PilotRankLimitCollection($request->page()->paginate(PilotRankLimit::query()->orderBy('pilot_rank')));
    }

    /**
     * PATCH /api/v1/pilot-rank-limits/{pilotRankLimit}: 200; 409 VERSION_CONFLICT; 422 (`errors.pilot_rank_limits.order`); 423.
     */
    public function update(UpdatePilotRankLimitRequest $request, PilotRankLimit $pilotRankLimit): PilotRankLimitResource
    {
        $loa = $request->validated('max_vessel_loa');
        $saved = $this->limits->update($pilotRankLimit, $loa === null ? null : (string) $loa, $request->validated('version'), $request->user()->id);

        return new PilotRankLimitResource($saved);
    }
}
