<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;

/**
 * Saves a new password and clears `must_change_password` in one transaction with its audit row (Coding Notes §6.1).
 */
class PasswordChangeService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  string  $newPassword  In plain text; the model hashes it.
     */
    public function change(User $user, string $newPassword): void
    {
        $this->audit->transaction(function () use ($user, $newPassword) {
            $wasRequired = $user->must_change_password;

            $user->forceFill([
                'password_hash' => $newPassword,
                'must_change_password' => false,
            ])->save();

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
