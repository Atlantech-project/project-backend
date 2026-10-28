<?php

namespace Database\Factories\Dispatch;

use App\Modules\Access\Models\User;
use App\Modules\Dispatch\Enums\NotificationLogChannel;
use App\Modules\Dispatch\Enums\NotificationLogRelatedEntityType;
use App\Modules\Dispatch\Enums\NotificationLogStatus;
use App\Modules\Dispatch\Enums\NotificationLogType;
use App\Modules\Dispatch\Models\NotificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
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
            'related_entity_type' => NotificationLogRelatedEntityType::PlanComponent,
            'related_entity_id' => fake()->numberBetween(1, 10),
            'type' => NotificationLogType::PlanAssignment,
            'channel' => NotificationLogChannel::Web,
            'status' => NotificationLogStatus::Sent,
            'failure_reason' => null,
            'sent_at' => now(),
            'acknowledged_at' => null,
        ];
    }
}
