<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;

/**
 * Cancelling a user's mobile devices and bringing one back (P2-E3, FR-AC-13). Rows are never deleted: `revoked_at` is set or cleared.
 */
class DeviceRevocationService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Revoke the device and all its live tokens. Revoking again changes nothing.
     */
    public function revoke(UserDevice $device, int $actorId): UserDevice
    {
        return $this->audit->transaction(function () use ($device, $actorId): UserDevice {
            $device = UserDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($device->revoked_at === null) {
                $this->revokeLocked($device, $actorId);
            }

            return $device;
        });
    }

    /**
     * Revoke every device of the user that is not revoked yet.
     *
     * @return array{devices_revoked: int, tokens_revoked: int}
     */
    public function revokeAll(User $user, int $actorId): array
    {
        return $this->audit->transaction(function () use ($user, $actorId): array {
            $devices = UserDevice::query()->where('user_id', $user->id)->whereNull('revoked_at')->orderBy('id')->lockForUpdate()->get();
            $tokens = 0;

            foreach ($devices as $device) {
                $tokens += $this->revokeLocked($device, $actorId);
            }

            return ['devices_revoked' => $devices->count(), 'tokens_revoked' => $tokens];
        });
    }

    /**
     * Make a revoked device a normal device again. Its revoked tokens stay revoked: the user signs in again.
     */
    public function restore(UserDevice $device, int $actorId): UserDevice
    {
        return $this->audit->transaction(function () use ($device, $actorId): UserDevice {
            $device = UserDevice::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($device->revoked_at !== null) {
                $device->update(['revoked_at' => null]);
                $this->audit->recordUpdated($device, $actorId);
            }

            return $device;
        });
    }

    private function revokeLocked(UserDevice $device, int $actorId): int
    {
        $now = now();

        $device->update(['revoked_at' => $now, 'push_token' => null, 'push_token_updated_at' => null]);

        $tokensRevoked = PersonalAccessToken::query()
            ->where('user_device_id', $device->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        $this->audit->record(
            AuditLogAction::Update,
            $device->getTable(),
            $device->id,
            $actorId,
            ['revoked_at' => null],
            ['revoked_at' => $device->revoked_at->toJSON(), 'tokens_revoked' => $tokensRevoked],
        );

        return $tokensRevoked;
    }
}
