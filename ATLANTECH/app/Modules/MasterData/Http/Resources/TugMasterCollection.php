<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class TugMasterCollection extends PaginatedCollection
{
    public $collects = TugMasterResource::class;
}
