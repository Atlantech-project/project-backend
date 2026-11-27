<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class TugboatCollection extends PaginatedCollection
{
    public $collects = TugboatResource::class;
}
