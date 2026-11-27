<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

class PilotRankLimitCollection extends PaginatedCollection
{
    public $collects = PilotRankLimitResource::class;
}
