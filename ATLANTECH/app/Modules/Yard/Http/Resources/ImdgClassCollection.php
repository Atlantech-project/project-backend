<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class ImdgClassCollection extends PaginatedCollection
{
    public $collects = ImdgClassResource::class;
}
