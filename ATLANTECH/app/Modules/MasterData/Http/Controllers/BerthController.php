<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Services\AccessRefusal;
use App\Modules\Access\Services\TerminalScope;
use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\MasterData\Http\Requests\BerthIndexRequest;
use App\Modules\MasterData\Http\Requests\BerthStatusRequest;
use App\Modules\MasterData\Http\Requests\StoreBerthRequest;
use App\Modules\MasterData\Http\Requests\UpdateBerthRequest;
use App\Modules\MasterData\Http\Resources\BerthCollection;
use App\Modules\MasterData\Http\Resources\BerthResource;
use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Services\BerthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Berths for the port set-up (P2-F2, FR-MD-04, FR-MD-05, DEC-51). Reads carry `ability:port-state.read`, writes
 * `ability:port-setup.manage` and `version.token:berth`. A terminal-scoped role sees only the berths linked to its terminal;
 * `{berth}` has no `{terminal}`, so `show` checks the scope itself (Q4).
 */
class BerthController extends Controller
{
    public function __construct(
        private readonly BerthService $berths,
        private readonly TerminalScope $terminalScope,
        private readonly AccessRefusal $refusal,
    ) {}

    /**
     * GET /api/v1/berths: a page ordered by code; filters `status`, `terminal_id` (scope checked), `q`; 401, 403, 422.
     */
    public function index(BerthIndexRequest $request): BerthCollection
    {
        $filters = $request->validated();
        $user = $request->user();

        if (isset($filters['terminal_id'])) {
            $this->terminalScope->authorize($request, $user, (int) $filters['terminal_id']);
        }

        $query = Berth::query()
            ->with('terminals')
            ->whereHas('berthTerminals', fn ($links) => $this->terminalScope->restrict($links, 'terminal_id', $user))
            ->when(isset($filters['terminal_id']), fn ($query) => $query->whereHas('berthTerminals', fn ($links) => $links->where('terminal_id', $filters['terminal_id'])))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['q']), function ($query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\%_').'%';

                return $query->where(fn ($match) => $match->where('code', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->orderBy('code')
            ->orderBy('id');

        return new BerthCollection($request->page()->paginate($query));
    }

    /**
     * GET /api/v1/berths/{berth}: the berth with its terminals; 401, 403 (another terminal's berth), 404.
     */
    public function show(Request $request, Berth $berth): BerthResource
    {
        $user = $request->user();
        $scope = $user->accessScope();

        if ($scope->isTerminalScoped() && ! $berth->berthTerminals()->whereIn('terminal_id', $scope->terminalIds)->exists()) {
            $this->refusal->refuse($request, $user, AccessRefusalReason::TerminalOutOfScope);
        }

        return new BerthResource($berth->load('terminals'));
    }

    /**
     * POST /api/v1/berths: 201 with the berth and its terminals; 401, 403, 422.
     */
    public function store(StoreBerthRequest $request): JsonResponse
    {
        $berth = $this->berths->create($request->validated(), $request->user()->id);

        return (new BerthResource($berth->load('terminals')))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/berths/{berth}: measurements, name, coordinates and terminals; 409 VERSION_CONFLICT or CONFLICT
     * (terminal in use); 422.
     */
    public function update(UpdateBerthRequest $request, Berth $berth): BerthResource
    {
        $data = $request->safe()->except('version');
        $updated = $this->berths->update($berth, $data, $request->validated('version'), $request->user(), $this->route($request));

        return new BerthResource($updated->load('terminals'));
    }

    /**
     * POST /api/v1/berths/{berth}/deactivate: status INACTIVE; 200 (also when already); 409 VERSION_CONFLICT.
     */
    public function deactivate(BerthStatusRequest $request, Berth $berth): BerthResource
    {
        return $this->changeStatus($request, $berth, FacilityStatus::Inactive);
    }

    /**
     * POST /api/v1/berths/{berth}/activate: status ACTIVE; 200 (also when already); 409 VERSION_CONFLICT.
     */
    public function activate(BerthStatusRequest $request, Berth $berth): BerthResource
    {
        return $this->changeStatus($request, $berth, FacilityStatus::Active);
    }

    /**
     * POST /api/v1/berths/{berth}/maintenance: status MAINTENANCE; 200 (also when already); 409 VERSION_CONFLICT.
     */
    public function maintenance(BerthStatusRequest $request, Berth $berth): BerthResource
    {
        return $this->changeStatus($request, $berth, FacilityStatus::Maintenance);
    }

    private function changeStatus(BerthStatusRequest $request, Berth $berth, FacilityStatus $status): BerthResource
    {
        $changed = $this->berths->changeStatus($berth, $status, $request->validated('version'), $request->user()->id);

        return new BerthResource($changed->load('terminals'));
    }

    private function route(Request $request): string
    {
        return $request->method().' /'.$request->path();
    }
}
