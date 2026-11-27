<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class YardZoneTypeCollection extends PaginatedCollection
{
    public $collects = YardZoneTypeResource::class;
}
