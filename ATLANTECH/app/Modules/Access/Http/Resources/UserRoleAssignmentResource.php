<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Access\Models\UserRoleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One role a user holds or held (DEC-36); `terminal_id` comes from the organization unit.
 *
 * @mixin UserRoleAssignment
 */
class UserRoleAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role->value,
            'organization_unit_id' => $this->organization_unit_id,
            'unit_type' => $this->unit_type->value,
            'terminal_id' => $this->organizationUnit?->terminal_id,
            'active_from' => $this->active_from,
            'active_to' => $this->active_to,
        ];
    }
}
