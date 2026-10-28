<?php

namespace Database\Factories\Access;

use App\Modules\Access\Enums\DevicePlatform;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => fake()->uuid(),
            'platform' => fake()->randomElement(DevicePlatform::cases()),
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
            'revoked_at' => null,
            'push_token' => null,
            'push_token_updated_at' => null,
        ];
    }

    /**
     * A revoked device: no push token may stay on it (`chk_ud_push_revoked`).
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['revoked_at' => now(), 'push_token' => null]);
    }
}
