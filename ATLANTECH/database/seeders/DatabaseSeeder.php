<?php

namespace Database\Seeders;

use App\Modules\Access\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. The reference rows and the demo port come in P1-B6.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'username' => 'test.user',
        ]);
    }
}
