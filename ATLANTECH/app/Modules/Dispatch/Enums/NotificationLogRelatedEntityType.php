<?php

namespace App\Modules\Dispatch\Enums;

/**
 * `notification_logs.related_entity_type`.
 */
enum NotificationLogRelatedEntityType: string
{
    case PlanComponent = 'PLAN_COMPONENT';
    case BerthingRequest = 'BERTHING_REQUEST';
    case Recommendation = 'RECOMMENDATION';
    case EntryWindowProposal = 'ENTRY_WINDOW_PROPOSAL';
    case Tugboat = 'TUGBOAT';
    case RetrainingRun = 'RETRAINING_RUN';
    case License = 'LICENSE';
}
