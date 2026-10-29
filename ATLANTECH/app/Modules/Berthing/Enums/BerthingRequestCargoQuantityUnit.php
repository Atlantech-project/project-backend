<?php

namespace App\Modules\Berthing\Enums;

/**
 * `berthing_request_cargos.quantity_unit`.
 */
enum BerthingRequestCargoQuantityUnit: string
{
    case Tonnes = 'TONNES';
    case CubicMetres = 'CUBIC_METRES';
    case Units = 'UNITS';
}
