<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Saves a new password and clears `must_change_password` in one transaction with its audit row (Coding Notes §6.1).
 */
class PasswordChangeService
{
    /**
     * @param  string  $newPassword  In plain text; the model hashes it.
     */
    public function change(User $user, string $newPassword): void
    {
        DB::transaction(function () use ($user, $newPassword) {
            $wasRequired = $user->must_change_password;

            $user->forceFill([
                'password_hash' => $newPassword,
                'must_change_password' => false,
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $user->id,
                'entity_type' => 'users',
                'entity_id' => $user->id,
                'action' => AuditLogAction::Update,
                'before_state' => ['must_change_password' => $wasRequired],
                'after_state' => ['must_change_password' => false, 'password_changed' => true],
            ]);
        });
    }
}
