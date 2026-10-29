<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Enums\UnitType;
use Database\Factories\Access\OrganizationUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where a user works: the Harbour Authority, Marine Services, or one terminal.
 * `terminal_id` stays a plain column here (MasterData comes after Access); `Terminal::organizationUnit()` links back.
 * `single_key` is generated and never written.
 */
#[Table(name: 'organization_units', timestamps: false)]
#[Fillable(['unit_type', 'terminal_id'])]
#[UseFactory(OrganizationUnitFactory::class)]
class OrganizationUnit extends Model
{
    /** @use HasFactory<OrganizationUnitFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_type' => UnitType::class,
        ];
    }

    /**
     * @return HasMany<UserRoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }
}
