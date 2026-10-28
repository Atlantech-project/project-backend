<?php

namespace Database\Factories\Access;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRoleAssignment>
 */
class UserRoleAssignmentFactory extends Factory
{
    /**
     * Define the model's default state: an active terminal role (the role must match the unit type,
     * `chk_ura_role_matches_unit`, and the unit type is kept equal to the unit's through `fk_ura_unit`).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => Role::TerminalOfficer,
            'organization_unit_id' => OrganizationUnit::factory(),
            'unit_type' => UnitType::Terminal,
            'active_from' => now(),
            'active_to' => null,
        ];
    }

    /**
     * An ended assignment (history row).
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => ['active_from' => now()->subMonth(), 'active_to' => now()->subDay()]);
    }
}
