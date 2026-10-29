<?php

namespace Database\Factories\Berthing;

use App\Modules\Berthing\Enums\BerthingRequestStatus;
use App\Modules\Berthing\Models\BerthingRequest;
use App\Modules\Berthing\Models\BerthingRequestStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerthingRequestStatusHistory>
 */
class BerthingRequestStatusHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'berthing_request_id' => BerthingRequest::factory(),
            'from_status' => null,
            'to_status' => BerthingRequestStatus::Submitted,
            'changed_by' => null,
            'changed_at' => now(),
            'note' => null,
        ];
    }
}
