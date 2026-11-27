<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class ShiftScheduleCollection extends PaginatedCollection
{
    public $collects = ShiftScheduleResource::class;
}
