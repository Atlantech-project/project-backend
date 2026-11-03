<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\Ability;
use App\Modules\Access\Enums\Role;

/**
 * The role-to-ability matrix (P2-D1). No inheritance between roles (a manager holds only the manager
 * functions). `MARINE_SAFETY_OFFICER` is Non-Container only and holds nothing until that work starts.
 * A change here changes the expected table in `RolePermissionsTest` and `contracts/role-matrix.md`.
 */
final class RolePermissions
{
    /**
     * @return list<Ability>
     */
    public static function abilitiesFor(Role $role): array
    {
        return match ($role) {
            Role::PortGm => [
                Ability::PortStateRead,
                Ability::UsersManage,
                Ability::DevicesManage,
                Ability::AuditRead,
                Ability::LicenseManage,
                Ability::AiModelReview,
                Ability::PlansSafetyException,
                Ability::PortSetupManage,
            ],
            Role::PortStaff => [
                Ability::PortStateRead,
                Ability::BerthingRequestsManage,
                Ability::BaplieManage,
                Ability::EntryWindowDecide,
                Ability::EnvironmentManualEntry,
                Ability::RecommendationsReview,
                Ability::PlansConfirm,
                Ability::PlansCancel,
                Ability::DeviationsReview,
            ],
            Role::MarineSafetyOfficer => [],
            Role::PilotageManager => [
                Ability::PortStateRead,
                Ability::PilotRosterManage,
            ],
            Role::PilotageOfficer => [
                Ability::PortStateRead,
                Ability::PilotageAssign,
            ],
            Role::MarinePilot,
            Role::TugMaster => [
                Ability::TasksOwnRead,
                Ability::TasksOwnUpdate,
            ],
            Role::TugManager => [
                Ability::PortStateRead,
                Ability::TugFleetManage,
            ],
            Role::TugOfficer => [
                Ability::PortStateRead,
                Ability::TugAssign,
            ],
            Role::TerminalAdmin => [
                Ability::PortStateRead,
                Ability::EquipmentManage,
                Ability::YardManage,
            ],
            Role::TerminalOfficer => [
                Ability::PortStateRead,
                Ability::YardRecommendationsDecide,
                Ability::TosExportRecord,
            ],
            Role::TerminalSupervisor => [
                Ability::TasksOwnRead,
                Ability::TasksOwnUpdate,
                Ability::TerminalStatusUpdate,
            ],
        };
    }

    public static function allows(Role $role, Ability $ability): bool
    {
        return in_array($ability, self::abilitiesFor($role), true);
    }
}
