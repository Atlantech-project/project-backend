<?php

namespace Database\Factories\Shared;

use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'entity_type' => fake()->lexify(str_repeat('?', 10)),
            'entity_id' => null,
            'action' => AuditLogAction::Create,
            'before_state' => null,
            'after_state' => null,
            'occurred_at' => now(),
        ];
    }
}
