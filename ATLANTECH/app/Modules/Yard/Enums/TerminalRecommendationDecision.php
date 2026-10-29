<?php

namespace App\Modules\Yard\Enums;

/**
 * `terminal_recommendations.decision`.
 */
enum TerminalRecommendationDecision: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
    case Adjusted = 'ADJUSTED';
}
