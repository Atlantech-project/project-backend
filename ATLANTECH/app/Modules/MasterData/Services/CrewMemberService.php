<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Access\Services\UserAdministrationService;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\UserLimitCheck;
use Illuminate\Support\Arr;

/**
 * The user side of a pilot or tug master (P2-F3, FR-MD-08, FR-MD-09, D-29): the account, its one role in Marine Services,
 * and active or not (Q4). Every method runs inside the caller's transaction, next to the profile change.
 */
class CrewMemberService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly UserAdministrationService $users,
        private readonly UserLimitCheck $userLimit,
    ) {}

    /**
     * A new active user (plan limit checked, one-time password) holding `$role` in Marine Services.
     *
     * @param  array{name: string, username: string, phone?: string|null}  $data
     * @return array{user: User, one_time_password: string}
     */
    public function createUser(array $data, Role $role, int $actorId): array
    {
        $created = $this->users->create(Arr::only($data, ['name', 'username', 'phone']), $actorId);
        $unit = OrganizationUnit::query()->firstOrCreate(['unit_type' => UnitType::MarineServices, 'terminal_id' => null]);

        $assignment = UserRoleAssignment::query()->create([
            'user_id' => $created['user']->id,
            'role' => $role,
            'organization_unit_id' => $unit->id,
            'unit_type' => $unit->unit_type,
            'active_from' => now()->startOfSecond(),
        ]);
        $this->audit->recordCreated($assignment, $actorId);

        return $created;
    }

    public function lockUser(int $userId): User
    {
        return User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array{name?: string, phone?: string|null}  $data
     * @return bool Whether the user changed.
     */
    public function updateUser(User $user, array $data, int $actorId): bool
    {
        $user->fill(Arr::only($data, ['name', 'phone']));

        if (! $user->isDirty()) {
            return false;
        }

        $user->save();
        $this->audit->recordUpdated($user, $actorId);

        return true;
    }

    /**
     * @return bool Whether the user changed.
     */
    public function setActive(User $user, bool $active, int $actorId): bool
    {
        if ($user->is_active === $active) {
            return false;
        }

        if ($active) {
            $this->userLimit->assertCanAddActiveUser();
        }

        $user->update(['is_active' => $active]);
        $this->audit->recordUpdated($user, $actorId);

        return true;
    }
}
