<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\UserLimitCheck;
use App\Modules\Shared\Support\VersionToken;

/**
 * Creating, deactivating and reactivating users (P2-E1, FR-AC-09, FR-AC-10).
 */
class UserAdministrationService
{
    private const string PASSWORD_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

    private const int PASSWORD_LENGTH = 20;

    public function __construct(
        private readonly AuditService $audit,
        private readonly PortAdminGuard $admins,
        private readonly UserLimitCheck $userLimit,
        private readonly RoleEndHandler $roleEnd,
        private readonly RoleStartHandler $roleStart,
    ) {}

    /**
     * @param  array{name: string, username: string, phone?: string|null}  $data
     * @return array{user: User, one_time_password: string}
     */
    public function create(array $data, int $actorId): array
    {
        return $this->audit->transaction(function () use ($data, $actorId): array {
            $this->userLimit->assertCanAddActiveUser();

            $password = $this->generatePassword();
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => $password,
                'is_active' => true,
                'must_change_password' => true,
            ]);
            $this->audit->recordCreated($user, $actorId);

            return ['user' => $user, 'one_time_password' => $password];
        });
    }

    /**
     * @param  string  $route  Method and path of the request, for the audit row of a refusal.
     */
    public function deactivate(User $target, string $version, User $actor, string $route): User
    {
        $outcome = $this->audit->transaction(function () use ($target, $version, $actor): User|string {
            $locked = $this->admins->lockAdminSet($target);
            $user = VersionToken::lockAndAssert($target, $version);

            if (! $user->is_active) {
                return $user;
            }

            if ($user->id === $actor->id) {
                return 'cannot_deactivate_self';
            }

            if ($this->admins->isLastActiveAdmin($locked, $user)) {
                return 'last_port_admin';
            }

            $user->update(['is_active' => false]);
            $this->audit->recordUpdated($user, $actor->id);

            $role = $user->activeRoleAssignment()->lockForUpdate()->first()?->role;

            if ($role !== null) {
                $this->roleEnd->roleEnded($user->id, $role, $actor->id);
            }

            return $user;
        });

        if (is_string($outcome)) {
            $this->admins->refuse($outcome, $actor, $target, $route);
        }

        return $outcome;
    }

    public function activate(User $target, string $version, User $actor): User
    {
        return $this->audit->transaction(function () use ($target, $version, $actor): User {
            $user = VersionToken::lockAndAssert($target, $version);

            if ($user->is_active) {
                return $user;
            }
            $this->userLimit->assertCanAddActiveUser();

            $user->update(['is_active' => true]);
            $this->audit->recordUpdated($user, $actor->id);

            $role = $user->activeRoleAssignment()->lockForUpdate()->first()?->role;

            if ($role !== null) {
                $this->roleStart->roleStarted($user->id, $role, $actor->id);
            }

            return $user;
        });
    }

    private function generatePassword(): string
    {
        $last = strlen(self::PASSWORD_ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < self::PASSWORD_LENGTH; $i++) {
            $password .= self::PASSWORD_ALPHABET[random_int(0, $last)];
        }

        return $password;
    }
}
