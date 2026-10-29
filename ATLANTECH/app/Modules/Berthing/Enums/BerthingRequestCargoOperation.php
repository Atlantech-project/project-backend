<?php

namespace App\Modules\Berthing\Enums;

/**
 * `berthing_request_cargos.operation`.
 */
enum BerthingRequestCargoOperation: string
{
    case Discharge = 'DISCHARGE';
    case Load = 'LOAD';
}
