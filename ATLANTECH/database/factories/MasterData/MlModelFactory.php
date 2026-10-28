<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\MlModelKey;
use App\Modules\MasterData\Models\MlModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MlModel>
 */
class MlModelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'model_key' => MlModelKey::M2ServiceTime,
            'algorithm_family' => fake()->lexify(str_repeat('?', 10)),
            'description' => null,
        ];
    }
}
