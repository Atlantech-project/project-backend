<?php

namespace Database\Seeders;

use App\Modules\MasterData\Models\PortProfile;
use Illuminate\Database\Seeder;

class PortProfileSeeder extends Seeder
{
    /**
     * The `public_id` is a placeholder for the SaaS registry value, which a provisioned deployment overwrites.
     */
    public const PUBLIC_ID = '7d0f6b1e-5a1c-4e0a-9a53-0d1b7e3c2f10';

    /**
     * Create the singleton `port_profiles` row (id 1) of the demo port. An existing row is left as it is: the SaaS
     * side owns it, and a production deployment gets it from provisioning, not from this seeder (P1-B6 K1).
     */
    public function run(): void
    {
        DemoPortSeeder::ensureAllowed();

        PortProfile::query()->firstOrCreate(['id' => 1], [
            'public_id' => self::PUBLIC_ID,
            'port_code' => 'EGDAM',
            'governorate_code' => 'DT',
            'name' => 'Damietta Port',
            'latitude' => 31.433300,
            'longitude' => 31.783300,
            'timezone' => 'Africa/Cairo',
        ]);
    }
}
