<?php

namespace App\Modules\Shared\Enums;

/**
 * `audit_logs.action`.
 */
enum AuditLogAction: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Delete = 'DELETE';
    case StatusChange = 'STATUS_CHANGE';
    case SafetyException = 'SAFETY_EXCEPTION';
    case RejectedAttempt = 'REJECTED_ATTEMPT';
}
