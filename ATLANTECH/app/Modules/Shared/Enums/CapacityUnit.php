<?php

namespace App\Modules\Shared\Enums;

/**
 * `storage_allocation_items.quantity_unit`, `storage_facilities.capacity_unit`.
 */
enum CapacityUnit: string
{
    case Tonnes = 'TONNES';
    case CubicMetres = 'CUBIC_METRES';
    case SquareMetres = 'SQUARE_METRES';
    case Teu = 'TEU';
    case Units = 'UNITS';
}
