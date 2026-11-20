<?php

namespace App\Modules\License\Services;

use App\Modules\Access\Models\User;
use App\Modules\License\Models\LicenseState;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\UserLimitCheck;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Reads `license_states.max_users` and counts the active users. The licence row is locked, so two requests cannot both take the last seat.
 */
class PlanUserLimit implements UserLimitCheck
{
    public function assertCanAddActiveUser(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('The plan limit is checked inside the transaction that adds the user.');
        }

        $maxUsers = LicenseState::query()->lockForUpdate()->value('max_users');

        if ($maxUsers === null) {
            return;
        }

        if (User::query()->where('is_active', true)->count() >= $maxUsers) {
            throw new ApiProblemException(ErrorCode::PlanLimitExceeded);
        }
    }
}
