<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Services\TerminalScope;
use App\Modules\MasterData\Http\Requests\StoreTerminalRequest;
use App\Modules\MasterData\Http\Requests\TerminalIndexRequest;
use App\Modules\MasterData\Http\Requests\TerminalStatusRequest;
use App\Modules\MasterData\Http\Requests\UpdateTerminalRequest;
use App\Modules\MasterData\Http\Resources\TerminalCollection;
use App\Modules\MasterData\Http\Resources\TerminalResource;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Services\TerminalService;
use Illuminate\Http\JsonResponse;

/**
 * Terminals for the port set-up (P2-F2, FR-MD-03, FR-MD-05). Reads carry `ability:port-state.read`, writes
 * `ability:port-setup.manage`; routes with `{terminal}` carry `terminal.scope`. A terminal-scoped role sees only its terminal.
 */
class TerminalController extends Controller
{
    public function __construct(
        private readonly TerminalService $terminals,
        private readonly TerminalScope $terminalScope,
    ) {}

    /**
     * GET /api/v1/terminals: a page ordered by code; filters `terminal_type`, `is_active`, `q`; 401, 403, 422.
     */
    public function index(TerminalIndexRequest $request): TerminalCollection
    {
        $filters = $request->validated();
        $query = Terminal::query()
            ->withCount('berths')
            ->when(isset($filters['terminal_type']), fn ($query) => $query->where('terminal_type', $filters['terminal_type']))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(isset($filters['q']), function ($query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\%_').'%';

                return $query->where(fn ($match) => $match->where('code', 'like', $like)->orWhere('name', 'like', $like));
            })
            ->orderBy('code')
            ->orderBy('id');

        return new TerminalCollection($request->page()->paginate($this->terminalScope->restrict($query, 'id', $request->user())));
    }

    /**
     * GET /api/v1/terminals/{terminal}: the terminal with its berths; 401, 403, 404.
     */
    public function show(Terminal $terminal): TerminalResource
    {
        return new TerminalResource($terminal->load('berths'));
    }

    /**
     * POST /api/v1/terminals: 201 with the terminal; its TERMINAL unit is created in the same transaction; 401, 403, 422.
     */
    public function store(StoreTerminalRequest $request): JsonResponse
    {
        $terminal = $this->terminals->create($request->validated(), $request->user()->id);

        return (new TerminalResource($terminal->load('berths')))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/terminals/{terminal}: name and boundary; 409 VERSION_CONFLICT; 422.
     */
    public function update(UpdateTerminalRequest $request, Terminal $terminal): TerminalResource
    {
        $data = $request->safe()->except('version');

        return new TerminalResource($this->terminals->update($terminal, $data, $request->validated('version'), $request->user()->id)->load('berths'));
    }

    /**
     * POST /api/v1/terminals/{terminal}/deactivate: 200 (also when already inactive); 409 VERSION_CONFLICT.
     */
    public function deactivate(TerminalStatusRequest $request, Terminal $terminal): TerminalResource
    {
        return new TerminalResource($this->terminals->deactivate($terminal, $request->validated('version'), $request->user()->id)->load('berths'));
    }

    /**
     * POST /api/v1/terminals/{terminal}/activate: 200 (also when already active); 409 VERSION_CONFLICT.
     */
    public function activate(TerminalStatusRequest $request, Terminal $terminal): TerminalResource
    {
        return new TerminalResource($this->terminals->activate($terminal, $request->validated('version'), $request->user()->id)->load('berths'));
    }
}
