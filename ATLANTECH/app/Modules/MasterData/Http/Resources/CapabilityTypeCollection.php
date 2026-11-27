<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class CapabilityTypeCollection extends PaginatedCollection
{
    public $collects = CapabilityTypeResource::class;
}
