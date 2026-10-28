<?php

namespace App\Modules\Yard\Enums;

/**
 * `storage_allocation_items.rec_type`, `terminal_recommendations.rec_type`, `yard_placement_items.rec_type`.
 */
enum TerminalRecommendationType: string
{
    case Timing = 'TIMING';
    case YardPlacement = 'YARD_PLACEMENT';
    case StorageAllocation = 'STORAGE_ALLOCATION';
}
