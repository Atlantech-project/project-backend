<?php

namespace App\Modules\Yard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\ReferenceListRequest;
use App\Modules\MasterData\Models\ImdgClass;
use App\Modules\Yard\Http\Resources\ImdgClassCollection;
use App\Modules\Yard\Http\Resources\YardZoneTypeCollection;
use App\Modules\Yard\Models\YardZoneType;

class YardReferenceController extends Controller
{
    /**
     * GET /api/v1/yard-zone-types: read-only reference list ordered by code (rows from reference-seed.json, CT-18).
     */
    public function zoneTypes(ReferenceListRequest $request): YardZoneTypeCollection
    {
        return new YardZoneTypeCollection($request->page()->paginate(YardZoneType::query()->orderBy('code')));
    }

    /**
     * GET /api/v1/imdg-classes: read-only reference list ordered by code.
     */
    public function imdgClasses(ReferenceListRequest $request): ImdgClassCollection
    {
        return new ImdgClassCollection($request->page()->paginate(ImdgClass::query()->orderBy('code')));
    }
}
