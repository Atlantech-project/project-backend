<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\StoreTugboatRequest;
use App\Modules\MasterData\Http\Requests\TugboatIndexRequest;
use App\Modules\MasterData\Http\Requests\UpdateTugboatRequest;
use App\Modules\MasterData\Http\Resources\TugboatCollection;
use App\Modules\MasterData\Http\Resources\TugboatResource;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Services\TugboatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * The tug fleet (P2-F3, FR-MD-07, P-45): read with `port-state.read`, written by the Tug Manager (`tug-fleet.manage`).
 */
class TugboatController extends Controller
{
    public function __construct(private readonly TugboatService $tugboats) {}

    /**
     * GET /api/v1/tugboats: a page ordered by name; filter `status`; 401, 403, 422.
     */
    public function index(TugboatIndexRequest $request): TugboatCollection
    {
        $query = Tugboat::query()
            ->with('tugMaster.user')
            ->when($request->has('status'), fn ($query) => $query->where('status', $request->validated('status')))
            ->orderBy('name')
            ->orderBy('id');

        return new TugboatCollection($request->page()->paginate($query));
    }

    /**
     * GET /api/v1/tugboats/{tugboat}: 200; 401, 403, 404.
     */
    public function show(Tugboat $tugboat): TugboatResource
    {
        return new TugboatResource($tugboat->load('tugMaster.user'));
    }

    /**
     * POST /api/v1/tugboats: 201; 401, 403, 422, 423.
     */
    public function store(StoreTugboatRequest $request): JsonResponse
    {
        $tugboat = $this->tugboats->create($request->validated(), $request->user()->id);

        return (new TugboatResource($tugboat->load('tugMaster.user')))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/tugboats/{tugboat}: 200; 409 VERSION_CONFLICT; 422; 423.
     */
    public function update(UpdateTugboatRequest $request, Tugboat $tugboat): TugboatResource
    {
        $data = Arr::except($request->validated(), ['version']);
        $saved = $this->tugboats->update($tugboat, $data, $request->validated('version'), $request->user()->id);

        return new TugboatResource($saved->load('tugMaster.user'));
    }
}
