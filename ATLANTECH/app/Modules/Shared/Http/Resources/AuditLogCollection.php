<?php

namespace App\Modules\Shared\Http\Resources;

/**
 * A page of audit rows in the CT-01 list shape.
 */
class AuditLogCollection extends PaginatedCollection
{
    public $collects = AuditLogEntryResource::class;
}
