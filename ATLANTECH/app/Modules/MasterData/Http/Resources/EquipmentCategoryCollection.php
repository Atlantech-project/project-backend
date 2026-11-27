<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class EquipmentCategoryCollection extends PaginatedCollection
{
    public $collects = EquipmentCategoryResource::class;
}
