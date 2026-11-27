<?php

namespace App\Modules\Yard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ReferenceListRequest;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Yard\Http\Requests\StoreContainerYardRequest;
use App\Modules\Yard\Http\Requests\UpdateContainerYardRequest;
use App\Modules\Yard\Http\Resources\ContainerYardCollection;
use App\Modules\Yard\Http\Resources\ContainerYardResource;
use App\Modules\Yard\Services\ContainerYardService;
use Illuminate\Http\JsonResponse;

class ContainerYardController extends Controller
{
    public function __construct(private readonly ContainerYardService $yards) {}

    /**
     * GET /api/v1/terminals/{terminal}/yards: the container yards ordered by code.
     */
    public function index(ReferenceListRequest $request, Terminal $terminal): ContainerYardCollection
    {
        $query = $terminal->storageFacilities()->where('detail_kind', StorageDetailKind::ContainerYard)->orderBy('code')->orderBy('id');

        return new ContainerYardCollection($request->page()->paginate($query));
    }

    public function show(Terminal $terminal, StorageFacility $containerYard): ContainerYardResource
    {
        return new ContainerYardResource($containerYard);
    }

    public function store(StoreContainerYardRequest $request, Terminal $terminal): JsonResponse
    {
        $yard = $this->yards->create($terminal, $request->validated(), $request->user()->id);

        return (new ContainerYardResource($yard))->response()->setStatusCode(201);
    }

    public function update(UpdateContainerYardRequest $request, Terminal $terminal, StorageFacility $containerYard): ContainerYardResource
    {
        $data = $request->safe()->except('version');

        return new ContainerYardResource($this->yards->update($containerYard, $data, $request->validated('version'), $request->user()->id));
    }
}
