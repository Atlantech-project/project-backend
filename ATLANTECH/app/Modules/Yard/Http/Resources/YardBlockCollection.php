<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class YardBlockCollection extends PaginatedCollection
{
    public $collects = YardBlockResource::class;
}
