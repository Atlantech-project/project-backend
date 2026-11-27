<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of productivity profiles in the CT-01 list shape.
 */
class ProductivityProfileCollection extends PaginatedCollection
{
    public $collects = ProductivityProfileResource::class;
}
