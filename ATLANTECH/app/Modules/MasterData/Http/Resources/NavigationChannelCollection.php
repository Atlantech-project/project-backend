<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of navigation channels in the CT-01 list shape.
 */
class NavigationChannelCollection extends PaginatedCollection
{
    public $collects = NavigationChannelResource::class;
}
