<?php

namespace App\Modules\Access\Enums;

/**
 * What a role may do, grouped by function area. Each route declares the one it needs
 * (`->middleware('ability:users.manage')`). The role-to-ability map is `RolePermissions` (P2-D1).
 * Not a database value, so not in the status vocabulary (CT-21).
 *
 * @not-stored
 */
enum Ability: string
{
    // Port picture (Office User)
    case PortStateRead = 'port-state.read';

    // Admin area
    case UsersManage = 'users.manage';
    case DevicesManage = 'devices.manage';
    case AuditRead = 'audit.read';
    case LicenseManage = 'license.manage';
    case AiModelReview = 'ai-model.review';

    // Safety exception
    case PlansSafetyException = 'plans.safety-exception';

    // Port set-up
    case PortSetupManage = 'port-setup.manage';

    // Pilotage
    case PilotRosterManage = 'pilot-roster.manage';
    case PilotageAssign = 'pilotage.assign';

    // Tugs
    case TugFleetManage = 'tug-fleet.manage';
    case TugAssign = 'tug.assign';

    // Requests and plans
    case BerthingRequestsManage = 'berthing-requests.manage';
    case BaplieManage = 'baplie.manage';
    case EntryWindowDecide = 'entry-window.decide';
    case EnvironmentManualEntry = 'environment.manual-entry';
    case RecommendationsReview = 'recommendations.review';
    case PlansConfirm = 'plans.confirm';
    case PlansCancel = 'plans.cancel';
    case DeviationsReview = 'deviations.review';

    // Terminal master data (own terminal)
    case EquipmentManage = 'equipment.manage';
    case YardManage = 'yard.manage';

    // Terminal planning (own terminal)
    case YardRecommendationsDecide = 'yard-recommendations.decide';
    case TosExportRecord = 'tos-export.record';

    // Field execution
    case TasksOwnRead = 'tasks.own.read';
    case TasksOwnUpdate = 'tasks.own.update';
    case TerminalStatusUpdate = 'terminal-status.update';
}
