<?php

namespace App\Modules\Shared\Support;

use App\Modules\Shared\Errors\ApiProblemException;

/**
 * The plan limit on active users (FR-AC-09, FR-LIC-04). Access calls this interface; the License module implements it, so Access never imports License.
 */
interface UserLimitCheck
{
    /**
     * Refuse when one more active user would pass the plan limit. Call it inside the transaction that adds or reactivates the user.
     *
     * @throws ApiProblemException 422 PLAN_LIMIT_EXCEEDED.
     */
    public function assertCanAddActiveUser(): void;
}
