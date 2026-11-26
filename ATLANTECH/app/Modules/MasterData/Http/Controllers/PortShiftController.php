<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\PageIndexRequest;
use App\Modules\MasterData\Http\Requests\StorePortShiftRequest;
use App\Modules\MasterData\Http\Requests\UpdatePortShiftRequest;
use App\Modules\MasterData\Http\Resources\PortShiftCollection;
use App\Modules\MasterData\Http\Resources\PortShiftResource;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Services\PortShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * Port shifts (P2-F3, P-62 shifts tab): read with `port-state.read`, written by the Port Admin (`port-setup.manage`, Q2).
 */
class PortShiftController extends Controller
{
    public function __construct(private readonly PortShiftService $shifts) {}

    /**
     * GET /api/v1/port-shifts: a page by start time; 401, 403, 422.
     */
    public function index(PageIndexRequest $request): PortShiftCollection
    {
        return new PortShiftCollection($request->page()->paginate(PortShift::query()->orderBy('start_time')->orderBy('id')));
    }

    /**
     * POST /api/v1/port-shifts: 201; 401, 403, 422 (`errors.port_shifts.overlap`), 423.
     */
    public function store(StorePortShiftRequest $request): JsonResponse
    {
        return (new PortShiftResource($this->shifts->create($request->validated(), $request->user()->id)))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/port-shifts/{portShift}: 200; 409 VERSION_CONFLICT or CONFLICT (`errors.port_shifts.in_use`);
     * 422 (`errors.port_shifts.overlap`); 423.
     */
    public function update(UpdatePortShiftRequest $request, PortShift $portShift): PortShiftResource
    {
        $data = Arr::except($request->validated(), ['version']);
        $route = $request->method().' /'.$request->path();

        return new PortShiftResource($this->shifts->update($portShift, $data, $request->validated('version'), $request->user(), $route));
    }
}
