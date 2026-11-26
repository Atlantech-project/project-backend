<?php

namespace App\Modules\Shared\Enums;

/**
 * The kind of resource a `ResourceChanged` event names. `BERTH`, `TUG` and `PILOT` are the CT-10 `changed_resources[].type` values;
 * `EQUIPMENT` is backend-only (P2-F7) and is not sent to Part 2 under CT-10 v1. Internal: not stored and not in the status vocabulary (CT-21).
 *
 * @not-stored
 */
enum ResourceKind: string
{
    case Berth = 'BERTH';
    case Tug = 'TUG';
    case Pilot = 'PILOT';
    case Equipment = 'EQUIPMENT';
}
