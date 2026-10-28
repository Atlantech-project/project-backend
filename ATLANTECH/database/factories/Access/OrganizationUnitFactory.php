<?php

namespace Database\Factories\Access;

use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\MasterData\Models\Terminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
{
    /**
     * Define the model's default state: a terminal unit, the only kind there can be many of
     * (`uq_org_unit_type_key` allows one Harbour Authority and one Marine Services unit).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit_type' => UnitType::Terminal,
            'terminal_id' => Terminal::factory(),
        ];
    }

    /**
     * The single Harbour Authority unit.
     */
    public function harbourAuthority(): static
    {
        return $this->state(fn (array $attributes) => ['unit_type' => UnitType::HarbourAuthority, 'terminal_id' => null]);
    }

    /**
     * The single Marine Services unit.
     */
    public function marineServices(): static
    {
        return $this->state(fn (array $attributes) => ['unit_type' => UnitType::MarineServices, 'terminal_id' => null]);
    }
}
