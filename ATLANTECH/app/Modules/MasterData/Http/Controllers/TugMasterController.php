<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\UserVersionRequest;
use App\Modules\MasterData\Http\Requests\AssignTugboatRequest;
use App\Modules\MasterData\Http\Requests\CrewIndexRequest;
use App\Modules\MasterData\Http\Requests\StoreTugMasterRequest;
use App\Modules\MasterData\Http\Requests\UpdateCrewMemberRequest;
use App\Modules\MasterData\Http\Resources\TugMasterCollection;
use App\Modules\MasterData\Http\Resources\TugMasterResource;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Services\TugMasterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * Tug masters (P2-F3, FR-MD-08, P-46): read with `port-state.read`, written by the Tug Manager (`tug-fleet.manage`).
 * `{tugMaster}` binds only a user with a tug master profile, so any other user answers 404 (Q5).
 */
class TugMasterController extends Controller
{
    public function __construct(private readonly TugMasterService $masters) {}

    /**
     * GET /api/v1/tug-masters: a page ordered by name; filters `is_active`, `q`; 401, 403, 422.
     */
    public function index(CrewIndexRequest $request): TugMasterCollection
    {
        $filters = $request->validated();

        $query = TugMasterProfile::query()
            ->select('tug_master_profiles.*')
            ->join('users', 'users.id', '=', 'tug_master_profiles.user_id')
            ->with(['user', 'assignedTugboat'])
            ->when(isset($filters['is_active']), fn ($query) => $query->where('users.is_active', $request->boolean('is_active')))
            ->when(isset($filters['q']), function ($query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\%_').'%';

                return $query->where(fn ($match) => $match->where('users.name', 'like', $like)->orWhere('users.username', 'like', $like));
            })
            ->orderBy('users.name')
            ->orderBy('users.id');

        return new TugMasterCollection($request->page()->paginate($query));
    }

    /**
     * GET /api/v1/tug-masters/{tugMaster}: 200; 401, 403, 404.
     */
    public function show(TugMasterProfile $tugMaster): TugMasterResource
    {
        return $this->resource($tugMaster);
    }

    /**
     * POST /api/v1/tug-masters: 201 with the one-time password, shown here only; 401, 403, 422 VALIDATION_FAILED
     * (`errors.tug_masters.tug_taken`) or PLAN_LIMIT_EXCEEDED, 423.
     */
    public function store(StoreTugMasterRequest $request): JsonResponse
    {
        $created = $this->masters->create($request->validated(), $request->user()->id);

        return response()
            ->json([...$this->resource($created['tug_master'])->resolve(), 'one_time_password' => $created['one_time_password']], 201)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * PATCH /api/v1/tug-masters/{tugMaster}: name, phone; 200; 409 VERSION_CONFLICT; 422; 423.
     */
    public function update(UpdateCrewMemberRequest $request, TugMasterProfile $tugMaster): TugMasterResource
    {
        $data = Arr::only($request->validated(), ['name', 'phone']);

        return $this->resource($this->masters->update($tugMaster, $data, $request->validated('version'), $request->user()->id));
    }

    /**
     * PUT /api/v1/tug-masters/{tugMaster}/tugboat: link, move or unlink (null); 200; 409 VERSION_CONFLICT;
     * 422 `errors.tug_masters.tug_taken`; 423.
     */
    public function assignTugboat(AssignTugboatRequest $request, TugMasterProfile $tugMaster): TugMasterResource
    {
        $tugboatId = $request->validated('tugboat_id');
        $saved = $this->masters->assignTugboat($tugMaster, $tugboatId === null ? null : (int) $tugboatId, $request->validated('version'), $request->user()->id);

        return $this->resource($saved);
    }

    /**
     * POST /api/v1/tug-masters/{tugMaster}/deactivate: clears the tug link; 200; 409 VERSION_CONFLICT; 423.
     */
    public function deactivate(UserVersionRequest $request, TugMasterProfile $tugMaster): TugMasterResource
    {
        return $this->resource($this->masters->setActive($tugMaster, false, $request->validated('version'), $request->user()->id));
    }

    /**
     * POST /api/v1/tug-masters/{tugMaster}/activate: 200; 409 VERSION_CONFLICT; 422 PLAN_LIMIT_EXCEEDED; 423.
     */
    public function activate(UserVersionRequest $request, TugMasterProfile $tugMaster): TugMasterResource
    {
        return $this->resource($this->masters->setActive($tugMaster, true, $request->validated('version'), $request->user()->id));
    }

    private function resource(TugMasterProfile $master): TugMasterResource
    {
        return new TugMasterResource($master->load(['user', 'assignedTugboat']));
    }
}
