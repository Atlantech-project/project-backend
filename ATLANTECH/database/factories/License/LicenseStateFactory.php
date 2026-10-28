<?php

namespace Database\Factories\License;

use App\Modules\License\Enums\LicenseMode;
use App\Modules\License\Enums\ReadOnlyReason;
use App\Modules\License\Models\LicenseState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseState>
 */
class LicenseStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => 1,
            'token' => null,
            'license_ref' => null,
            'plan_name' => null,
            'max_terminals' => null,
            'max_berths' => null,
            'max_users' => null,
            'license_expires_at' => null,
            'token_expires_at' => null,
            'port_status' => null,
            'license_status' => null,
            'status_received_at' => null,
            'last_check_in_attempt_at' => null,
            'last_successful_check_in_at' => null,
            'last_check_in_error' => null,
            'max_seen_time' => null,
            'mode' => LicenseMode::ReadOnly,
            'read_only_reason' => ReadOnlyReason::NoLicense,
        ];
    }
}
