<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\UserVersionRequest;
use App\Modules\MasterData\Http\Requests\CrewIndexRequest;
use App\Modules\MasterData\Http\Requests\StorePilotRequest;
use App\Modules\MasterData\Http\Requests\UpdateCrewMemberRequest;
use App\Modules\MasterData\Http\Resources\PilotCollection;
use App\Modules\MasterData\Http\Resources\PilotResource;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Services\PilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

/**
 * The pilot roster (P2-F3, FR-MD-09, P-43): read with `port-state.read`, written by the Pilotage Manager
 * (`pilot-roster.manage`). `{pilot}` binds only a user with a pilot profile, so any other user answers 404 (Q5).
 */
class PilotController extends Controller
{
    public function __construct(private readonly PilotService $pilots) {}

    /**
     * GET /api/v1/pilots: a page ordered by name; filters `is_active`, `pilot_rank`, `q`; 401, 403, 422.
     */
    public function index(CrewIndexRequest $request): PilotCollection
    {
        $filters = $request->validated();

        $query = PilotProfile::query()
            ->select('pilot_profiles.*')
            ->join('users', 'users.id', '=', 'pilot_profiles.user_id')
            ->with('user')
            ->when(isset($filters['pilot_rank']), fn ($query) => $query->where('pilot_profiles.pilot_rank', $filters['pilot_rank']))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('users.is_active', $request->boolean('is_active')))
            ->when(isset($filters['q']), function ($query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\%_').'%';

                return $query->where(fn ($match) => $match->where('users.name', 'like', $like)->orWhere('users.username', 'like', $like));
            })
            ->orderBy('users.name')
            ->orderBy('users.id');

        return new PilotCollection($request->page()->paginate($query));
    }

    /**
     * GET /api/v1/pilots/{pilot}: 200; 401, 403, 404.
     */
    public function show(PilotProfile $pilot): PilotResource
    {
        return new PilotResource($pilot->load('user'));
    }

    /**
     * POST /api/v1/pilots: 201 with the one-time password, shown here only; 401, 403, 422 VALIDATION_FAILED or
     * PLAN_LIMIT_EXCEEDED, 423.
     */
    public function store(StorePilotRequest $request): JsonResponse
    {
        $created = $this->pilots->create($request->validated(), $request->user()->id);

        return response()
            ->json([...(new PilotResource($created['pilot']->load('user')))->resolve(), 'one_time_password' => $created['one_time_password']], 201)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * PATCH /api/v1/pilots/{pilot}: name, phone, rank (promotion); 200; 409 VERSION_CONFLICT; 422; 423.
     */
    public function update(UpdateCrewMemberRequest $request, PilotProfile $pilot): PilotResource
    {
        $data = Arr::except($request->validated(), ['version']);

        return new PilotResource($this->pilots->update($pilot, $data, $request->validated('version'), $request->user()->id)->load('user'));
    }

    /**
     * POST /api/v1/pilots/{pilot}/deactivate: 200; 409 VERSION_CONFLICT; 423.
     */
    public function deactivate(UserVersionRequest $request, PilotProfile $pilot): PilotResource
    {
        return new PilotResource($this->pilots->setActive($pilot, false, $request->validated('version'), $request->user()->id)->load('user'));
    }

    /**
     * POST /api/v1/pilots/{pilot}/activate: 200; 409 VERSION_CONFLICT; 422 PLAN_LIMIT_EXCEEDED; 423.
     */
    public function activate(UserVersionRequest $request, PilotProfile $pilot): PilotResource
    {
        return new PilotResource($this->pilots->setActive($pilot, true, $request->validated('version'), $request->user()->id)->load('user'));
    }
}
