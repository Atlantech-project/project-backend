<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class PortShiftCollection extends PaginatedCollection
{
    public $collects = PortShiftResource::class;
}
