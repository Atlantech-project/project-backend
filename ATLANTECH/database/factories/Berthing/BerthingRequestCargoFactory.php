<?php

namespace Database\Factories\Berthing;

use App\Modules\Berthing\Enums\BerthingRequestCargoOperation;
use App\Modules\Berthing\Enums\BerthingRequestCargoQuantityUnit;
use App\Modules\Berthing\Models\BerthingRequestCargo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerthingRequestCargo>
 */
class BerthingRequestCargoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'berthing_request_id' => fake()->numberBetween(1, 1_000_000),
            'terminal_id' => fake()->numberBetween(1, 1_000_000),
            'cargo_type_id' => fake()->numberBetween(1, 1_000_000),
            'operation' => BerthingRequestCargoOperation::Discharge,
            'quantity' => fake()->randomFloat(2, 1, 9),
            'quantity_unit' => BerthingRequestCargoQuantityUnit::Tonnes,
        ];
    }
}
