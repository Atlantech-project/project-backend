<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Giving a user a role and ending it (P2-E2, FR-AC-11, FR-AC-12, DEC-36: one active role, history kept).
 */
class RoleAssignmentService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly PortAdminGuard $admins,
        private readonly RoleEndHandler $roleEnd,
    ) {}

    /**
     * @param  string  $route  Method and path of the request, for the audit row of a refusal.
     *
     * @throws ValidationException 422 when a terminal role names a terminal that has no organization unit.
     */
    public function assign(User $target, Role $role, ?int $terminalId, string $version, User $actor, string $route): User
    {
        $outcome = $this->audit->transaction(function () use ($target, $role, $terminalId, $version, $actor): User|string {
            $locked = $this->admins->lockAdminSet($target);
            $user = VersionToken::lockAndAssert($target, $version);
            $current = $this->activeAssignment($user);
            $unit = $this->unitFor($role, $terminalId);

            if ($current?->role === $role && $current->organization_unit_id === $unit->id) {
                return $user;
            }

            if ($current?->role === Role::PortGm && $this->admins->isLastActiveAdmin($locked, $user)) {
                return 'last_port_admin';
            }

            $boundary = $this->roleSwitchTime($current);

            if ($current !== null) {
                $this->close($current, $boundary, $actor);
            }

            $assignment = UserRoleAssignment::query()->create([
                'user_id' => $user->id,
                'role' => $role,
                'organization_unit_id' => $unit->id,
                'unit_type' => $unit->unit_type,
                'active_from' => $boundary,
            ]);
            $this->audit->recordCreated($assignment, $actor->id);
            $user->touch();

            if ($current !== null && $current->role !== $role) {
                $this->roleEnd->roleEnded($user->id, $current->role, $actor->id);
            }

            return $user;
        });

        if (is_string($outcome)) {
            $this->admins->refuse($outcome, $actor, $target, $route);
        }

        return $outcome;
    }

    /**
     * @param  string  $route  Method and path of the request, for the audit row of a refusal.
     */
    public function end(User $target, string $version, User $actor, string $route): User
    {
        $outcome = $this->audit->transaction(function () use ($target, $version, $actor): User|string {
            $locked = $this->admins->lockAdminSet($target);
            $user = VersionToken::lockAndAssert($target, $version);
            $current = $this->activeAssignment($user);

            if ($current === null) {
                return $user;
            }

            if ($current->role === Role::PortGm && $this->admins->isLastActiveAdmin($locked, $user)) {
                return 'last_port_admin';
            }

            $this->close($current, $this->roleSwitchTime($current), $actor);
            $user->touch();
            $this->roleEnd->roleEnded($user->id, $current->role, $actor->id);

            return $user;
        });

        if (is_string($outcome)) {
            $this->admins->refuse($outcome, $actor, $target, $route);
        }

        return $outcome;
    }

    private function activeAssignment(User $user): ?UserRoleAssignment
    {
        return UserRoleAssignment::query()->where('user_id', $user->id)->whereNull('active_to')->lockForUpdate()->first();
    }

    private function close(UserRoleAssignment $assignment, CarbonInterface $endedAt, User $actor): void
    {
        $assignment->update(['active_to' => $endedAt]);
        $this->audit->recordUpdated($assignment, $actor->id);
    }

    private function roleSwitchTime(?UserRoleAssignment $current): CarbonInterface
    {
        $now = now()->startOfSecond();

        return $current === null ? $now : $now->max($current->active_from->copy()->addSecond());
    }

    private function unitFor(Role $role, ?int $terminalId): OrganizationUnit
    {
        $type = $this->unitTypeOf($role);

        if ($type === UnitType::Terminal) {
            return OrganizationUnit::query()->where('unit_type', $type)->where('terminal_id', $terminalId)->first()
                ?? throw ValidationException::withMessages(['terminal_id' => ['validation.exists']]);
        }

        return OrganizationUnit::query()->firstOrCreate(['unit_type' => $type, 'terminal_id' => null]);
    }

    private function unitTypeOf(Role $role): UnitType
    {
        return match ($role) {
            Role::PortGm, Role::PortStaff, Role::MarineSafetyOfficer => UnitType::HarbourAuthority,
            Role::TerminalAdmin, Role::TerminalOfficer, Role::TerminalSupervisor => UnitType::Terminal,
            default => UnitType::MarineServices,
        };
    }
}
