<?php

namespace Database\Factories\Access;

use App\Modules\Access\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The hashed password shared by every factory user.
     */
    protected static ?string $passwordHash;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'user.'.Str::lower(Str::random(10)),
            'password_hash' => static::$passwordHash ??= Hash::make('password'),
            'phone' => null,
            'is_active' => true,
            'must_change_password' => false,
        ];
    }

    /**
     * A disabled account (CT-01 `ACCOUNT_DISABLED`).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
