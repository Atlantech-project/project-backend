<?php

namespace Database\Factories\Plan;

use App\Modules\MasterData\Models\Berth;
use App\Modules\Plan\Enums\PlanComponentReservationState;
use App\Modules\Plan\Models\OperationalPlan;
use App\Modules\Plan\Models\PlanComponent;
use App\Modules\Shared\Enums\ComponentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanComponent>
 */
class PlanComponentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operational_plan_id' => OperationalPlan::factory(),
            'component_type' => ComponentType::Berth,
            'berth_id' => Berth::factory(),
            'tugboat_id' => null,
            'terminal_id' => null,
            'assigned_user_id' => null,
            'assigned_by' => null,
            'assigned_at' => null,
            'planned_start' => now(),
            'planned_end' => now()->addHours(12),
            'reservation_state' => PlanComponentReservationState::Reserved,
        ];
    }
}
