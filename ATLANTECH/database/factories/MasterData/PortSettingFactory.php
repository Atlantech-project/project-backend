<?php

namespace Database\Factories\MasterData;

use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortSetting>
 */
class PortSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'setting_key' => strtoupper(Str::random(10)),
            'setting_value' => fake()->lexify(str_repeat('?', 10)),
            'value_type' => PortSettingValueType::Int,
            'description' => null,
            'updated_by' => null,
        ];
    }
}
