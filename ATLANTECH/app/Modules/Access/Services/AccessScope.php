<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\User;

/**
 * What a signed-in user may reach: the one active role (DEC-36) and, for a terminal assignment, the terminal ids.
 * Port-wide roles have no terminal ids and are not restricted by terminal. A user with no active role has a null
 * role and no terminals. Built once per user instance by `User::accessScope()`.
 */
final readonly class AccessScope
{
    /**
     * @param  list<int>  $terminalIds
     */
    public function __construct(
        public int $userId,
        public ?Role $role,
        public array $terminalIds,
    ) {}

    public static function for(User $user): self
    {
        $user->loadMissing('activeRoleAssignment.organizationUnit');

        $assignment = $user->activeRoleAssignment;
        $terminalId = $assignment?->unit_type === UnitType::Terminal ? $assignment->organizationUnit?->terminal_id : null;

        return new self($user->id, $assignment?->role, $terminalId === null ? [] : [(int) $terminalId]);
    }

    public function hasRole(): bool
    {
        return $this->role !== null;
    }

    public function isTerminalScoped(): bool
    {
        return $this->role?->isTerminalScoped() ?? false;
    }

    public function canReachTerminal(int $terminalId): bool
    {
        return ! $this->isTerminalScoped() || in_array($terminalId, $this->terminalIds, true);
    }
}
