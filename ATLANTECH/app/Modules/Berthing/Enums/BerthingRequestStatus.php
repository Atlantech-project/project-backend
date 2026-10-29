<?php

namespace App\Modules\Berthing\Enums;

/**
 * `berthing_request_status_histories.from_status`, `berthing_request_status_histories.to_status`, `berthing_requests.status`.
 */
enum BerthingRequestStatus: string
{
    case Submitted = 'SUBMITTED';
    case NeedsRevision = 'NEEDS_REVISION';
    case PreliminaryApproved = 'PRELIMINARY_APPROVED';
    case CustomsApproved = 'CUSTOMS_APPROVED';
    case EntryWindowConfirmed = 'ENTRY_WINDOW_CONFIRMED';
    case RecommendationGenerated = 'RECOMMENDATION_GENERATED';
    case PlanConfirmed = 'PLAN_CONFIRMED';
    case PlanCancelled = 'PLAN_CANCELLED';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';
    case Cancelled = 'CANCELLED';
}
