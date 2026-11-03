<?php

namespace Database\Seeders;

use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Models\TugMasterSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo users for the Damietta demo port: one user for each of the 12 roles, 12 pilots, 10 tugs with their masters,
 * and seven days of pilot and tug-master shifts. Names are fake and come from the fixture. Every user must change
 * the password at first sign-in. The shared password is `seeding.demo_user_password` (DEMO_USER_PASSWORD); without
 * it the hash is random, so nobody can sign in. Run after `DemoPortSeeder`.
 */
class DemoPeopleSeeder extends Seeder
{
    private string $passwordHash;

    public function run(): void
    {
        $people = (new DemoPortSeeder)->fixture()['people'];
        $password = config('seeding.demo_user_password');
        $this->passwordHash = Hash::make(filled($password) ? $password : Str::random(40));

        DB::transaction(function () use ($people): void {
            foreach ($people['role_users'] as $row) {
                $this->assignRole($this->user($row['username'], $row['name']), $row['role'], $this->unit($row['unit']));
            }

            $marineServices = $this->unit('MARINE_SERVICES');

            foreach ($people['pilots'] as $row) {
                $user = $this->user($row['username'], $row['name']);
                $this->assignRole($user, 'MARINE_PILOT', $marineServices);
                PilotProfile::query()->firstOrCreate(['user_id' => $user->id], ['pilot_rank' => $row['pilot_rank']]);
            }

            foreach ($people['tugs'] as $row) {
                $tug = Tugboat::query()->firstOrCreate(['name' => $row['name']], collect($row)->only(['tug_power', 'tug_power_unit', 'capacity', 'status'])->all());
                $user = $this->user($row['master_username'], $row['master_name']);
                $this->assignRole($user, 'TUG_MASTER', $marineServices);
                TugMasterProfile::query()->firstOrCreate(['user_id' => $user->id], ['assigned_tugboat_id' => $tug->id]);
            }

            $this->seedSchedules($people);
        });
    }

    private function user(string $username, string $name): User
    {
        return User::query()->firstOrCreate(['username' => $username], [
            'name' => $name,
            'password_hash' => $this->passwordHash,
            'is_active' => true,
            'must_change_password' => true,
        ]);
    }

    /**
     * @param  string  $unit  HARBOUR_AUTHORITY, MARINE_SERVICES, or a terminal code
     */
    private function unit(string $unit): OrganizationUnit
    {
        if (in_array($unit, ['HARBOUR_AUTHORITY', 'MARINE_SERVICES'], true)) {
            return OrganizationUnit::query()->where('unit_type', $unit)->firstOrFail();
        }

        return OrganizationUnit::query()
            ->where('unit_type', 'TERMINAL')
            ->where('terminal_id', Terminal::query()->where('code', $unit)->value('id'))
            ->firstOrFail();
    }

    private function assignRole(User $user, string $role, OrganizationUnit $unit): void
    {
        $exists = UserRoleAssignment::query()
            ->where(['user_id' => $user->id, 'role' => $role, 'organization_unit_id' => $unit->id])
            ->whereNull('active_to')
            ->exists();

        if (! $exists) {
            UserRoleAssignment::query()->create([
                'user_id' => $user->id,
                'role' => $role,
                'organization_unit_id' => $unit->id,
                'unit_type' => $unit->unit_type,
                'active_from' => now(),
            ]);
        }
    }

    /**
     * Each person works one shift a day, rotating through the shifts, starting today in port time.
     *
     * @param  array<string, mixed>  $people
     */
    private function seedSchedules(array $people): void
    {
        $shiftIds = PortShift::query()->orderBy('start_time')->pluck('id')->values();
        $start = Carbon::today('Africa/Cairo');
        $pilotIds = User::query()->whereIn('username', array_column($people['pilots'], 'username'))->orderBy('username')->pluck('id')->values();
        $masterIds = User::query()->whereIn('username', array_column($people['tugs'], 'master_username'))->orderBy('username')->pluck('id')->values();
        $pilotsBy = User::query()->where('username', $people['scheduled_by']['pilots'])->value('id');
        $mastersBy = User::query()->where('username', $people['scheduled_by']['tug_masters'])->value('id');

        for ($day = 0; $day < $people['schedule_days']; $day++) {
            $date = $start->copy()->addDays($day)->toDateString();

            foreach ($pilotIds as $index => $id) {
                PilotSchedule::query()->firstOrCreate(
                    ['pilot_user_id' => $id, 'shift_date' => $date, 'port_shift_id' => $shiftIds[($index + $day) % $shiftIds->count()]],
                    ['created_by' => $pilotsBy],
                );
            }

            foreach ($masterIds as $index => $id) {
                TugMasterSchedule::query()->firstOrCreate(
                    ['tug_master_user_id' => $id, 'shift_date' => $date, 'port_shift_id' => $shiftIds[($index + $day) % $shiftIds->count()]],
                    ['created_by' => $mastersBy],
                );
            }
        }
    }
}
