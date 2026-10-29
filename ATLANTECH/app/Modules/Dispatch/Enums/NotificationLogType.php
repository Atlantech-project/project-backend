<?php

namespace App\Modules\Dispatch\Enums;

/**
 * `notification_logs.type`.
 */
enum NotificationLogType: string
{
    case PlanAssignment = 'PLAN_ASSIGNMENT';
    case PreExecutionReminder = 'PRE_EXECUTION_REMINDER';
    case TugStillBusy = 'TUG_STILL_BUSY';
    case EntryWindowReady = 'ENTRY_WINDOW_READY';
    case RecommendationReady = 'RECOMMENDATION_READY';
    case PlanCancelled = 'PLAN_CANCELLED';
    case RetrainingReviewNeeded = 'RETRAINING_REVIEW_NEEDED';
    case LicenseExpiryWarning = 'LICENSE_EXPIRY_WARNING';
    case AckEscalation = 'ACK_ESCALATION';
    case YardRecommendationReady = 'YARD_RECOMMENDATION_READY';
    case YardRecommendationFailed = 'YARD_RECOMMENDATION_FAILED';
    case DriftAlert = 'DRIFT_ALERT';
}
