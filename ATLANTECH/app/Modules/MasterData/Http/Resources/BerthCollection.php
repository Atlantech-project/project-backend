<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of berths in the CT-01 list shape.
 */
class BerthCollection extends PaginatedCollection
{
    public $collects = BerthResource::class;
}
