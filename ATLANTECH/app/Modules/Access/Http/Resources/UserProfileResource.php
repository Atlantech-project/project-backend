<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user's profile (CT-03): one active role (DEC-36) and the terminal scope it gives.
 * A user with no active role gets `role` null and no terminals; other endpoints refuse them with 403 (lead, 2026-10-29).
 *
 * @mixin User
 */
class UserProfileResource extends JsonResource
{
    /**
     * CT-03 returns the profile as the whole body, not under "data".
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * @return array{id: int, name: string, role: string|null, terminal_ids: list<int>, must_change_password: bool}
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->activeRoleAssignment;
        $terminalId = $assignment?->unit_type === UnitType::Terminal ? $assignment->organizationUnit?->terminal_id : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $assignment?->role->value,
            'terminal_ids' => $terminalId === null ? [] : [(int) $terminalId],
            'must_change_password' => $this->must_change_password,
        ];
    }
}
