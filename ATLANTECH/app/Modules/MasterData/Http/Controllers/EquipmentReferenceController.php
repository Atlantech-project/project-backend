<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ReferenceListRequest;
use App\Modules\MasterData\Http\Resources\CapabilityTypeCollection;
use App\Modules\MasterData\Http\Resources\EquipmentCategoryCollection;
use App\Modules\MasterData\Models\CapabilityType;
use App\Modules\MasterData\Models\EquipmentCategory;

/**
 * Read-only reference lists for the equipment screens (P2-F4, Q6). Their rows come from `reference-seed.json` (CT-18).
 */
class EquipmentReferenceController extends Controller
{
    /**
     * GET /api/v1/equipment-categories: a page ordered by code; 401, 403, 422.
     */
    public function categories(ReferenceListRequest $request): EquipmentCategoryCollection
    {
        return new EquipmentCategoryCollection($request->page()->paginate(EquipmentCategory::query()->orderBy('code')));
    }

    /**
     * GET /api/v1/capability-types: a page ordered by code; 401, 403, 422.
     */
    public function capabilityTypes(ReferenceListRequest $request): CapabilityTypeCollection
    {
        return new CapabilityTypeCollection($request->page()->paginate(CapabilityType::query()->orderBy('code')));
    }
}
