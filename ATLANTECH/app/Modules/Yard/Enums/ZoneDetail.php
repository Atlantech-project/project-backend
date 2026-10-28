<?php

namespace App\Modules\Yard\Enums;

/**
 * `dg_zone_allowed_classes.zone_detail`, `oog_zone_details.zone_detail`, `reefer_zone_details.zone_detail`, `yard_zone_types.zone_detail`, `yard_zones.zone_detail`.
 */
enum ZoneDetail: string
{
    case None = 'NONE';
    case Reefer = 'REEFER';
    case DangerousGoods = 'DANGEROUS_GOODS';
    case Oog = 'OOG';
}
