<?php

namespace Database\Seeders;

use App\Modules\Access\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Reference rows and the demo port: P1-B6.
     */
    public function run(): void
    {
        if (! app()->isProduction()) {
            User::query()->firstOrCreate(['username' => 'test.user'], User::factory()->make([
                'name' => 'Test User',
                'username' => 'test.user',
            ])->getAttributes());
        }

        $this->call([
            PortProfileSeeder::class,
            PortSettingsSeeder::class,
            ReferenceDataSeeder::class,
        ]);

        if (config('seeding.demo_port')) {
            $this->call([DemoPortSeeder::class, DemoPeopleSeeder::class]);
        }
    }
}
