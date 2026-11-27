<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class PilotCollection extends PaginatedCollection
{
    public $collects = PilotResource::class;
}
