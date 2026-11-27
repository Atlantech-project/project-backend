<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class ContainerYardCollection extends PaginatedCollection
{
    public $collects = ContainerYardResource::class;
}
