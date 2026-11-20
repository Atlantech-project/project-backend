<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of users in the CT-01 list shape.
 */
class UserCollection extends PaginatedCollection
{
    public $collects = UserResource::class;
}
