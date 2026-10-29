<?php

namespace App\Modules\Shared\Enums;

/**
 * `storage_facilities.detail_kind`, `storage_types.detail_kind`, `tank_details.detail_kind`, `warehouse_details.detail_kind`, `yard_zones.detail_kind`.
 */
enum StorageDetailKind: string
{
    case None = 'NONE';
    case Warehouse = 'WAREHOUSE';
    case ContainerYard = 'CONTAINER_YARD';
    case Tank = 'TANK';
}
