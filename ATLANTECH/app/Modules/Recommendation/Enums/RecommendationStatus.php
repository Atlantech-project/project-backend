<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `recommendations.status`.
 */
enum RecommendationStatus: string
{
    case PendingReview = 'PENDING_REVIEW';
    case Confirmed = 'CONFIRMED';
    case Rejected = 'REJECTED';
    case Invalidated = 'INVALIDATED';
    case Replaced = 'REPLACED';
    case ModifiedByStaff = 'MODIFIED_BY_STAFF';
}
