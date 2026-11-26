<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\Shared\Http\Resources\PaginatedCollection;

/**
 * A page of terminals in the CT-01 list shape.
 */
class TerminalCollection extends PaginatedCollection
{
    public $collects = TerminalResource::class;
}
