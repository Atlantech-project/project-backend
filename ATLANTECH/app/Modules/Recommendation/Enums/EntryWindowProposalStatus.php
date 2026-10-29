<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `entry_window_proposals.status`.
 */
enum EntryWindowProposalStatus: string
{
    case Proposed = 'PROPOSED';
    case Confirmed = 'CONFIRMED';
    case Replaced = 'REPLACED';
    case Rejected = 'REJECTED';
}
