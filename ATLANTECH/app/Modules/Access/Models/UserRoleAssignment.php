<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use Database\Factories\Access\UserRoleAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A role a user holds in one organisation unit. Never deleted: `active_to` ends it (DEC-36).
 * `active_assignment_key` is generated (one active row per user) and never written.
 */
#[Table(name: 'user_role_assignments', timestamps: false)]
#[Fillable(['user_id', 'role', 'organization_unit_id', 'unit_type', 'active_from', 'active_to'])]
#[UseFactory(UserRoleAssignmentFactory::class)]
class UserRoleAssignment extends Model
{
    /** @use HasFactory<UserRoleAssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'unit_type' => UnitType::class,
            'active_from' => 'datetime',
            'active_to' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OrganizationUnit, $this>
     */
    public function organizationUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class);
    }
}
