<?php

namespace App\Modules\Yard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ReferenceListRequest;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Yard\Http\Requests\StoreYardBlockRequest;
use App\Modules\Yard\Http\Requests\UpdateYardBlockRequest;
use App\Modules\Yard\Http\Requests\YardBlockLayoutRequest;
use App\Modules\Yard\Http\Requests\YardVersionRequest;
use App\Modules\Yard\Http\Resources\YardBlockCollection;
use App\Modules\Yard\Http\Resources\YardBlockResource;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardZone;
use App\Modules\Yard\Services\YardBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class YardBlockController extends Controller
{
    public function __construct(private readonly YardBlockService $blocks) {}

    /**
     * GET /api/v1/terminals/{terminal}/yard-zones/{yardZone}/blocks: blocks ordered by code, with their size.
     */
    public function index(ReferenceListRequest $request, Terminal $terminal, YardZone $yardZone): YardBlockCollection
    {
        $query = YardBlock::query()
            ->where('yard_zone_id', $yardZone->id)
            ->withCount('yardSlots')
            ->withMax('yardSlots', 'bay')
            ->withMax('yardSlots', 'row_no')
            ->withMax('yardSlots', 'tier')
            ->orderBy('code')
            ->orderBy('id');

        return new YardBlockCollection($request->page()->paginate($query));
    }

    public function show(Terminal $terminal, YardBlock $yardBlock): YardBlockResource
    {
        return $this->resource($yardBlock);
    }

    public function store(StoreYardBlockRequest $request, Terminal $terminal, YardZone $yardZone): JsonResponse
    {
        $block = $this->blocks->create($yardZone, $request->validated('code'), $request->user()->id);

        return $this->resource($block)->response()->setStatusCode(201);
    }

    public function update(UpdateYardBlockRequest $request, Terminal $terminal, YardBlock $yardBlock): YardBlockResource
    {
        return $this->resource($this->blocks->update($yardBlock, $request->validated('code'), $request->validated('version'), $request->user()->id));
    }

    public function layout(YardBlockLayoutRequest $request, Terminal $terminal, YardBlock $yardBlock): YardBlockResource
    {
        $block = $this->blocks->resize(
            $yardBlock,
            (int) $request->validated('bays'),
            (int) $request->validated('rows'),
            (int) $request->validated('tiers'),
            $request->validated('version'),
            $request->user(),
            $request->method().' /'.$request->path(),
        );

        return $this->resource($block);
    }

    public function destroy(YardVersionRequest $request, Terminal $terminal, YardBlock $yardBlock): Response
    {
        $this->blocks->delete($yardBlock, $request->validated('version'), $request->user(), $request->method().' /'.$request->path());

        return response()->noContent();
    }

    private function resource(YardBlock $block): YardBlockResource
    {
        $block->loadCount('yardSlots')->loadMax('yardSlots', 'bay')->loadMax('yardSlots', 'row_no')->loadMax('yardSlots', 'tier');

        return new YardBlockResource($block);
    }
}
