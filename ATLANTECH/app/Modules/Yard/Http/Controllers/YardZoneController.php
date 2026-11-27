<?php

namespace App\Modules\Yard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ReferenceListRequest;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Yard\Http\Requests\StoreYardZoneRequest;
use App\Modules\Yard\Http\Requests\UpdateYardZoneRequest;
use App\Modules\Yard\Http\Requests\YardVersionRequest;
use App\Modules\Yard\Http\Resources\YardZoneCollection;
use App\Modules\Yard\Http\Resources\YardZoneResource;
use App\Modules\Yard\Models\YardZone;
use App\Modules\Yard\Services\YardZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class YardZoneController extends Controller
{
    public function __construct(private readonly YardZoneService $zones) {}

    /**
     * GET /api/v1/terminals/{terminal}/yards/{containerYard}/zones: zones ordered by code, with detail and capacity.
     */
    public function index(ReferenceListRequest $request, Terminal $terminal, StorageFacility $containerYard): YardZoneCollection
    {
        $query = YardZone::query()
            ->where('container_yard_id', $containerYard->id)
            ->with(YardZoneResource::RELATIONS)
            ->withCount(YardZoneResource::slotCounts())
            ->orderBy('code')
            ->orderBy('id');

        return new YardZoneCollection($request->page()->paginate($query));
    }

    public function show(Terminal $terminal, YardZone $yardZone): YardZoneResource
    {
        return $this->resource($yardZone);
    }

    public function store(StoreYardZoneRequest $request, Terminal $terminal, StorageFacility $containerYard): JsonResponse
    {
        $zone = $this->zones->create($containerYard, $request->validated(), $request->user()->id);

        return $this->resource($zone)->response()->setStatusCode(201);
    }

    public function update(UpdateYardZoneRequest $request, Terminal $terminal, YardZone $yardZone): YardZoneResource
    {
        $data = $request->safe()->except('version');

        return $this->resource($this->zones->update($yardZone, $data, $request->validated('version'), $request->user()->id));
    }

    public function destroy(YardVersionRequest $request, Terminal $terminal, YardZone $yardZone): Response
    {
        $this->zones->delete($yardZone, $request->validated('version'), $request->user(), $request->method().' /'.$request->path());

        return response()->noContent();
    }

    private function resource(YardZone $zone): YardZoneResource
    {
        return new YardZoneResource($zone->load(YardZoneResource::RELATIONS)->loadCount(YardZoneResource::slotCounts()));
    }
}
