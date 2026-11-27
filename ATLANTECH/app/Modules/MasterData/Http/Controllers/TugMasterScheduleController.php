<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ShiftScheduleIndexRequest;
use App\Modules\MasterData\Http\Requests\StoreShiftScheduleRequest;
use App\Modules\MasterData\Http\Resources\ShiftScheduleCollection;
use App\Modules\MasterData\Http\Resources\ShiftScheduleResource;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\MasterData\Services\ShiftScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Tug master schedules (P2-F3, FR-MD-08, P-46): read with `port-state.read`, written by the Tug Manager (`tug-fleet.manage`).
 */
class TugMasterScheduleController extends Controller
{
    public function __construct(private readonly ShiftScheduleService $schedules) {}

    /**
     * GET /api/v1/tug-master-schedules: a page by date, shift start and tug master; required `from`, `to`; 401, 403, 422.
     */
    public function index(ShiftScheduleIndexRequest $request): ShiftScheduleCollection
    {
        $filters = $request->validated();

        $query = TugMasterSchedule::query()
            ->select('tug_master_schedules.*')
            ->join('port_shifts', 'port_shifts.id', '=', 'tug_master_schedules.port_shift_id')
            ->with(['tugMasterUser.user', 'portShift'])
            ->whereBetween('shift_date', [$filters['from'], $filters['to']])
            ->when(isset($filters['user_id']), fn ($query) => $query->where('tug_master_user_id', $filters['user_id']))
            ->when(isset($filters['port_shift_id']), fn ($query) => $query->where('port_shift_id', $filters['port_shift_id']))
            ->orderBy('shift_date')
            ->orderBy('port_shifts.start_time')
            ->orderBy('tug_master_schedules.id');

        return new ShiftScheduleCollection($request->page()->paginate($query));
    }

    /**
     * POST /api/v1/tug-master-schedules: 201 with the saved entries; 401, 403, 422 (`errors.schedules.*` on `entries.N.*`), 423.
     */
    public function store(StoreShiftScheduleRequest $request): JsonResponse
    {
        $created = $this->schedules->addTugMasterEntries($request->validated('entries'), $request->user()->id);

        return response()->json(['data' => ShiftScheduleResource::collection($created->load(['tugMasterUser.user', 'portShift']))->resolve()], 201);
    }

    /**
     * DELETE /api/v1/tug-master-schedules/{tugMasterSchedule}: 204; 401, 403, 404, 422 (`errors.schedules.past_date`), 423.
     */
    public function destroy(Request $request, TugMasterSchedule $tugMasterSchedule): Response
    {
        $this->schedules->remove($tugMasterSchedule, $request->user()->id);

        return response()->noContent();
    }
}
