<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Enums\DevicePlatform;
use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues the mobile token after `SignInService` accepted the credentials (P1-C3, CT-04): registers the device, revokes the device's
 * older token (one live token per device, lead's default D4) and creates a 30-day token named with the device id (Coding Notes §6.3).
 * A successful sign-in writes no audit row (lead, Q10).
 */
class MobileSignInService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  string  $route  Method and path of the request, for the audit row.
     *
     * @throws ApiProblemException 403 DEVICE_REVOKED when the Port Admin revoked this device (lead, 2026-11-01).
     */
    public function issueToken(User $user, string $deviceId, DevicePlatform $platform, string $appVersion, string $route): NewAccessToken
    {
        $result = DB::transaction(function () use ($user, $deviceId, $platform, $appVersion): NewAccessToken|UserDevice {
            $device = $this->lockedDevice($user, $deviceId);

            if ($device?->revoked_at !== null) {
                // Refused after the transaction, so the audit row is not rolled back with it.
                return $device;
            }

            $device ??= $this->registerDevice($user, $deviceId, $platform, $appVersion);
            $device->fill(['platform' => $platform, 'app_version' => $appVersion, 'last_seen_at' => now()])->save();

            PersonalAccessToken::query()
                ->where('user_device_id', $device->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            return $this->newToken($user, $device);
        });

        if ($result instanceof UserDevice) {
            $this->audit->rejectedAttempt($route, ErrorCode::DeviceRevoked, 'device_revoked', $user->id, $user->username, 'user_devices', $result->id);

            throw new ApiProblemException(ErrorCode::DeviceRevoked);
        }

        return $result;
    }

    private function lockedDevice(User $user, string $deviceId): ?UserDevice
    {
        return UserDevice::query()
            ->where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * First sign-in on this device. Two requests can race to insert the row; the loser hits `uq_user_device` and takes the winner's row
     * (MySQL keeps the transaction open after a duplicate key). The row is not revoked: it was just created.
     */
    private function registerDevice(User $user, string $deviceId, DevicePlatform $platform, string $appVersion): UserDevice
    {
        try {
            return UserDevice::query()->create(['user_id' => $user->id, 'device_id' => $deviceId, 'platform' => $platform, 'app_version' => $appVersion, 'last_seen_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            return $this->lockedDevice($user, $deviceId) ?? throw new ApiProblemException(ErrorCode::ServiceUnavailable);
        }
    }

    private function newToken(User $user, UserDevice $device): NewAccessToken
    {
        $plainTextToken = $user->generateTokenString();

        /** @var PersonalAccessToken $token */
        $token = $user->tokens()->create([
            'name' => $device->device_id,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(PersonalAccessToken::LIFETIME_DAYS),
            'user_device_id' => $device->id,
        ]);
        $token->setRelation('tokenable', $user);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
