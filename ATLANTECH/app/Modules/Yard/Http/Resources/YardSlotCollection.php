<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class YardSlotCollection extends PaginatedCollection
{
    public $collects = YardSlotResource::class;
}
