<?php

namespace Database\Seeders;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Enums\UnitType;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\Shared\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * The first Port Admin of a real port (P2-E1), from `.env` values: FIRST_PORT_ADMIN_USERNAME, _NAME and _PASSWORD. Does nothing unless all
 * three are set and no user holds an active PORT_GM role, so running it again is safe. The password is never printed.
 */
class FirstPortAdminSeeder extends Seeder
{
    public function run(AuditService $audit): void
    {
        $settings = config('seeding.first_port_admin');

        if (in_array(null, $settings, true) || in_array('', $settings, true)) {
            $this->command?->info('First Port Admin: FIRST_PORT_ADMIN_* are not all set, skipped.');

            return;
        }

        if (UserRoleAssignment::query()->where('role', Role::PortGm)->whereNull('active_to')->exists()) {
            $this->command?->info('First Port Admin: an active Port Admin exists, skipped.');

            return;
        }

        $audit->transaction(function () use ($audit, $settings): void {
            $unit = OrganizationUnit::query()->firstOrCreate(['unit_type' => UnitType::HarbourAuthority, 'terminal_id' => null]);

            $user = User::query()->create([
                'name' => $settings['name'],
                'username' => strtolower($settings['username']),
                'password_hash' => $settings['password'],
                'is_active' => true,
                'must_change_password' => true,
            ]);

            UserRoleAssignment::query()->create([
                'user_id' => $user->id,
                'role' => Role::PortGm,
                'organization_unit_id' => $unit->id,
                'unit_type' => UnitType::HarbourAuthority,
                'active_from' => now()->startOfSecond(),
            ]);

            $audit->recordCreated($user, null);
        });

        $this->command?->info('First Port Admin created; the password must be changed at first sign-in.');
    }
}
