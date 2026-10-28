<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Models\StorageType;
use App\Modules\Shared\Enums\StorageDetailKind;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StorageType>
 */
class StorageTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(10)),
            'name' => fake()->lexify(str_repeat('?', 10)),
            'detail_kind' => StorageDetailKind::None,
        ];
    }
}
