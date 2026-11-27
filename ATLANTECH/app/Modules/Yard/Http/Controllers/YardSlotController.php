<?php

namespace App\Modules\Yard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Http\Requests\UpdateYardSlotRequest;
use App\Modules\Yard\Http\Requests\YardSlotIndexRequest;
use App\Modules\Yard\Http\Requests\YardStackStatusRequest;
use App\Modules\Yard\Http\Resources\YardSlotCollection;
use App\Modules\Yard\Http\Resources\YardSlotResource;
use App\Modules\Yard\Models\YardBlock;
use App\Modules\Yard\Models\YardSlot;
use App\Modules\Yard\Services\YardSlotService;
use Illuminate\Http\JsonResponse;

class YardSlotController extends Controller
{
    public function __construct(private readonly YardSlotService $slots) {}

    /**
     * GET /api/v1/terminals/{terminal}/yard-blocks/{yardBlock}/slots: ordered by bay, row and tier; filters.
     */
    public function index(YardSlotIndexRequest $request, Terminal $terminal, YardBlock $yardBlock): YardSlotCollection
    {
        $filters = $request->safe()->only(['bay', 'row_no', 'tier', 'status']);
        $query = $yardBlock->yardSlots()->where($filters)->orderBy('bay')->orderBy('row_no')->orderBy('tier');

        return new YardSlotCollection($request->page()->paginate($query));
    }

    public function update(UpdateYardSlotRequest $request, Terminal $terminal, YardSlot $yardSlot): YardSlotResource
    {
        $slot = $this->slots->setStatus(
            $yardSlot,
            YardSlotStatus::from($request->validated('status')),
            $request->validated('version'),
            $request->user(),
            $request->method().' /'.$request->path(),
        );

        return new YardSlotResource($slot);
    }

    /**
     * POST /api/v1/terminals/{terminal}/yard-blocks/{yardBlock}/stack-status: 200 with every slot of the stack.
     */
    public function stack(YardStackStatusRequest $request, Terminal $terminal, YardBlock $yardBlock): JsonResponse
    {
        $stack = $this->slots->setStackStatus(
            $yardBlock,
            (int) $request->validated('bay'),
            (int) $request->validated('row_no'),
            YardSlotStatus::from($request->validated('status')),
            $request->user(),
            $request->method().' /'.$request->path(),
        );

        return response()->json(['data' => YardSlotResource::collection($stack)->resolve($request)]);
    }
}
