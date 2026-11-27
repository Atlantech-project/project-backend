<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of port settings in the CT-01 list shape.
 */
class PortSettingCollection extends PaginatedCollection
{
    public $collects = PortSettingResource::class;
}
