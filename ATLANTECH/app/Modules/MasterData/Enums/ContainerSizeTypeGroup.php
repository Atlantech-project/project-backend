<?php

namespace App\Modules\MasterData\Enums;

/**
 * `container_size_types.type_group`.
 */
enum ContainerSizeTypeGroup: string
{
    case General = 'GENERAL';
    case Reefer = 'REEFER';
    case Tank = 'TANK';
    case OpenTop = 'OPEN_TOP';
    case FlatRack = 'FLAT_RACK';
    case Ventilated = 'VENTILATED';
    case Bulk = 'BULK';
}
