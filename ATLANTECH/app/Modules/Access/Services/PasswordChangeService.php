<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;

/**
 * Saves a new password and clears `must_change_password` in one transaction with its audit row (Coding Notes §6.1).
 * The user's other sessions close, as on the web (lead's decision D-A, 2026-11-05): the other web sessions end by themselves when the
 * password hash changes (Sanctum's `AuthenticateSession`), and every other mobile token gets `revoked_at` here. The token of the
 * request that made the change stays, whether it came from the web or from a tablet. Token rows are kept (FR-GL-03).
 */
class PasswordChangeService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  string  $newPassword  In plain text; the model hashes it.
     * @param  PersonalAccessToken|null  $currentToken  The mobile token of the request, kept; null for a web session.
     */
    public function change(User $user, string $newPassword, ?PersonalAccessToken $currentToken = null): void
    {
        $this->audit->transaction(function () use ($user, $newPassword, $currentToken) {
            $wasRequired = $user->must_change_password;

            $user->forceFill([
                'password_hash' => $newPassword,
                'must_change_password' => false,
            ])->save();

            PersonalAccessToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey())
                ->whereNull('revoked_at')
                ->when($currentToken !== null, fn ($query) => $query->whereKeyNot($currentToken->getKey()))
                ->update(['revoked_at' => now()]);

            $this->audit->record(
                AuditLogAction::Update,
                $user->getTable(),
                $user->id,
                $user->id,
                ['must_change_password' => $wasRequired],
                ['must_change_password' => false, 'password_changed' => true],
            );
        });
    }
}
