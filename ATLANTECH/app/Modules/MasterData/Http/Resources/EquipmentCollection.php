<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class EquipmentCollection extends PaginatedCollection
{
    public $collects = EquipmentResource::class;
}
