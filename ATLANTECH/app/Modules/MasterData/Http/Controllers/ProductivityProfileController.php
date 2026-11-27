<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ProductivityProfileIndexRequest;
use App\Modules\MasterData\Http\Requests\StoreProductivityProfileRequest;
use App\Modules\MasterData\Http\Requests\UpdateProductivityProfileRequest;
use App\Modules\MasterData\Http\Resources\ProductivityProfileCollection;
use App\Modules\MasterData\Http\Resources\ProductivityProfileResource;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\TerminalProductivityProfile;
use App\Modules\MasterData\Services\ProductivityProfileService;
use Illuminate\Http\JsonResponse;

/**
 * A terminal's productivity profiles (P2-F6, FR-MD-13, P-48). Reads carry `ability:port-state.read`, writes
 * `ability:equipment.manage`; every route carries `terminal.scope`. A profile of another terminal in the path is 404.
 */
class ProductivityProfileController extends Controller
{
    public function __construct(private readonly ProductivityProfileService $profiles) {}

    /**
     * GET: a page ordered by start date; 401, 403, 404, 422.
     */
    public function index(ProductivityProfileIndexRequest $request, Terminal $terminal): ProductivityProfileCollection
    {
        $query = TerminalProductivityProfile::query()->where('terminal_id', $terminal->id)->orderBy('valid_from')->orderBy('id');

        return new ProductivityProfileCollection($request->page()->paginate($query));
    }

    /**
     * POST: 201; 401, 403, 404, 409 (inactive terminal), 422.
     */
    public function store(StoreProductivityProfileRequest $request, Terminal $terminal): JsonResponse
    {
        $profile = $this->profiles->create($terminal, $request->validated(), $request->user()->id);

        return (new ProductivityProfileResource($profile))->response()->setStatusCode(201);
    }

    /**
     * PUT: 404, 409 VERSION_CONFLICT or CONFLICT, 422.
     */
    public function update(UpdateProductivityProfileRequest $request, Terminal $terminal, TerminalProductivityProfile $terminalProductivityProfile): ProductivityProfileResource
    {
        $data = $request->safe()->except('version');

        return new ProductivityProfileResource($this->profiles->update($terminal, $terminalProductivityProfile, $data, $request->validated('version'), $request->user()->id));
    }
}
