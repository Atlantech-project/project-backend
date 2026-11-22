<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of a user's devices in the CT-01 list shape.
 */
class UserDeviceCollection extends PaginatedCollection
{
    public $collects = UserDeviceResource::class;
}
