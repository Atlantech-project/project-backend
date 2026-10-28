<?php

namespace App\Modules\Berthing\Enums;

/**
 * `berthing_requests.draft_source`, `berthing_requests.eta_source`, `berthing_requests.ports_source`.
 */
enum DataSource: string
{
    case Manual = 'MANUAL';
    case EgmswFeed = 'EGMSW_FEED';
    case Registry = 'REGISTRY';
}
