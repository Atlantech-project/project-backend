<?php

namespace App\Modules\Access\Enums;

/**
 * Why a role, terminal or own-task check refused a request; written to the audit row's `after_state.reason` (FR-GL-02).
 */
enum AccessRefusalReason: string
{
    case NoActiveRole = 'no_active_role';
    case RoleNotAllowed = 'role_not_allowed';
    case TerminalOutOfScope = 'terminal_out_of_scope';
    case NotOwnTask = 'not_own_task';
}
