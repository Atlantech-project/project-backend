<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class YardZoneCollection extends PaginatedCollection
{
    public $collects = YardZoneResource::class;
}
