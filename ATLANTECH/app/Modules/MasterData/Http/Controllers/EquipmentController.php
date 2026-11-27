<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\EquipmentIndexRequest;
use App\Modules\MasterData\Http\Requests\StoreEquipmentRequest;
use App\Modules\MasterData\Http\Requests\UpdateEquipmentRequest;
use App\Modules\MasterData\Http\Resources\EquipmentCollection;
use App\Modules\MasterData\Http\Resources\EquipmentResource;
use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Services\EquipmentService;
use Illuminate\Http\JsonResponse;

class EquipmentController extends Controller
{
    private const array RELATIONS = ['equipmentCategory', 'equipmentCapabilities.capabilityType'];

    public function __construct(private readonly EquipmentService $equipment) {}

    /**
     * GET /api/v1/terminals/{terminal}/equipment: a page ordered by category; filter `equipment_category_id`; 401, 403, 404, 422.
     */
    public function index(EquipmentIndexRequest $request, Terminal $terminal): EquipmentCollection
    {
        $filters = $request->validated();
        $query = $terminal->equipment()
            ->with(self::RELATIONS)
            ->when(isset($filters['equipment_category_id']), fn ($query) => $query->where('equipment_category_id', $filters['equipment_category_id']))
            ->orderBy('equipment_category_id')
            ->orderBy('id');

        return new EquipmentCollection($request->page()->paginate($query));
    }

    /**
     * GET /api/v1/terminals/{terminal}/equipment/{equipment}: 401, 403, 404.
     */
    public function show(Terminal $terminal, Equipment $equipment): EquipmentResource
    {
        return new EquipmentResource($equipment->load(self::RELATIONS));
    }

    /**
     * POST /api/v1/terminals/{terminal}/equipment: 201; 401, 403, 409 inactive terminal, 422.
     */
    public function store(StoreEquipmentRequest $request, Terminal $terminal): JsonResponse
    {
        $equipment = $this->equipment->create($terminal, $request->validated(), $request->user()->id);

        return (new EquipmentResource($equipment->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/terminals/{terminal}/equipment/{equipment}: 409 VERSION_CONFLICT or inactive terminal; 422.
     */
    public function update(UpdateEquipmentRequest $request, Terminal $terminal, Equipment $equipment): EquipmentResource
    {
        $data = $request->safe()->except('version');
        $updated = $this->equipment->update($terminal, $equipment, $data, $request->validated('version'), $request->user()->id);

        return new EquipmentResource($updated->load(self::RELATIONS));
    }
}
