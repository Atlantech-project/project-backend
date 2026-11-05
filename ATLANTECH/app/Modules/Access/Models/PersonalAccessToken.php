<?php

namespace App\Modules\Access\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use LogicException;

/**
 * A mobile device token (P1-C3, CT-04). It slides: each accepted use moves `expires_at` to 30 days later (FR-AC-06), stamped by
 * `EnsureTokenDevice` once the device matches, not by Sanctum. It is never deleted: `revoked_at` is set instead (FR-GL-03), and
 * Sanctum refuses a revoked token (AccessServiceProvider).
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public const int LIFETIME_DAYS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'user_device_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Record an accepted use: stamp it and move the expiry to 30 days from now, in one write.
     */
    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now(), 'expires_at' => now()->addDays(self::LIFETIME_DAYS)])->save();
    }

    /**
     * @return BelongsTo<UserDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Revoke the token; it then answers 401. Revoking twice keeps the first time.
     */
    public function revoke(): void
    {
        if (! $this->isRevoked()) {
            $this->forceFill(['revoked_at' => now()])->save();
        }
    }

    /**
     * Tokens are never deleted (FR-GL-03): use revoke().
     *
     * @throws LogicException always.
     */
    public function delete(): never
    {
        throw new LogicException('Tokens are never deleted (FR-GL-03): call revoke().');
    }
}
