<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Events\ResourceChanged;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Who works which port shift on which port-local date (P2-F3, FR-MD-08, FR-MD-09, Q7): pilot schedules and tug master
 * schedules follow the same rules. A batch is saved whole or not at all. An entry is refused when its date is before
 * today in port time, the person is inactive or no longer holds the role, the shift is inactive, or the entry exists.
 * The person rows are locked so the same entry cannot be added twice at once; the shift rows are read under a shared
 * lock, so a shift cannot be deactivated or retimed while entries for it are being added (Q8). Adding or removing
 * entries dispatches `ResourceChanged` once per resource (P2-F7): the pilot, or the tug master's tug if one is linked.
 */
class ShiftScheduleService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  list<array{user_id: int, shift_date: string, port_shift_id: int}>  $entries
     * @return Collection<int, PilotSchedule>
     *
     * @throws ValidationException 422 on `entries.N.*`.
     */
    public function addPilotEntries(array $entries, int $actorId): Collection
    {
        return $this->add(PilotSchedule::class, PilotProfile::class, 'pilot_user_id', Role::MarinePilot, $entries, $actorId);
    }

    /**
     * @param  list<array{user_id: int, shift_date: string, port_shift_id: int}>  $entries
     * @return Collection<int, TugMasterSchedule>
     *
     * @throws ValidationException 422 on `entries.N.*`.
     */
    public function addTugMasterEntries(array $entries, int $actorId): Collection
    {
        return $this->add(TugMasterSchedule::class, TugMasterProfile::class, 'tug_master_user_id', Role::TugMaster, $entries, $actorId);
    }

    /**
     * The entry is re-read under a row lock, so two removals at once delete and audit it once; the second answers 404.
     *
     * @throws ValidationException 422 `errors.schedules.past_date` on `shift_date`: past entries are history.
     * @throws ModelNotFoundException 404 when another request removed the entry first.
     */
    public function remove(PilotSchedule|TugMasterSchedule $entry, int $actorId): void
    {
        $this->audit->transaction(function () use ($entry, $actorId): void {
            $locked = $entry->newQuery()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->shift_date->toDateString() < PortClock::today()) {
                throw ValidationException::withMessages(['shift_date' => ['errors.schedules.past_date']]);
            }

            $locked->delete();
            $this->audit->recordDeleted($locked, $actorId);

            $userId = $locked instanceof PilotSchedule ? $locked->pilot_user_id : $locked->tug_master_user_id;
            $this->announceScheduleChange($locked::class, [$userId], $actorId);
        });
    }

    /**
     * @param  class-string<PilotSchedule|TugMasterSchedule>  $scheduleClass
     * @param  class-string<PilotProfile|TugMasterProfile>  $profileClass
     * @param  list<array{user_id: int, shift_date: string, port_shift_id: int}>  $entries
     * @return Collection<int, Model>
     */
    private function add(string $scheduleClass, string $profileClass, string $userColumn, Role $role, array $entries, int $actorId): Collection
    {
        return $this->audit->transaction(function () use ($scheduleClass, $profileClass, $userColumn, $role, $entries, $actorId): Collection {
            $userIds = array_values(array_unique(array_column($entries, 'user_id')));

            $profileClass::query()->whereKey($userIds)->lockForUpdate()->get();
            $users = User::query()->whereKey($userIds)->with('activeRoleAssignment')->get()->keyBy('id');
            $shifts = PortShift::query()->whereKey(array_column($entries, 'port_shift_id'))->sharedLock()->get()->keyBy('id');
            $today = PortClock::today();
            $seen = [];
            $errors = [];

            foreach ($entries as $index => $entry) {
                $user = $users->get($entry['user_id']);
                $key = $entry['user_id'].'|'.$entry['shift_date'].'|'.$entry['port_shift_id'];

                if ($entry['shift_date'] < $today) {
                    $errors["entries.{$index}.shift_date"][] = 'errors.schedules.past_date';
                }

                if (! $user->is_active || $user->activeRoleAssignment?->role !== $role) {
                    $errors["entries.{$index}.user_id"][] = 'errors.schedules.person_unavailable';
                }

                if (! $shifts->get($entry['port_shift_id'])->is_active) {
                    $errors["entries.{$index}.port_shift_id"][] = 'errors.schedules.shift_inactive';
                }

                $exists = $scheduleClass::query()
                    ->where($userColumn, $entry['user_id'])
                    ->where('shift_date', $entry['shift_date'])
                    ->where('port_shift_id', $entry['port_shift_id'])
                    ->exists();

                if ($exists || isset($seen[$key])) {
                    $errors["entries.{$index}.shift_date"][] = 'errors.schedules.duplicate';
                }

                $seen[$key] = true;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
            $created = new Collection;

            foreach ($entries as $entry) {
                $schedule = $scheduleClass::query()->create([
                    $userColumn => $entry['user_id'],
                    'shift_date' => $entry['shift_date'],
                    'port_shift_id' => $entry['port_shift_id'],
                    'created_by' => $actorId,
                ]);
                $this->audit->recordCreated($schedule, $actorId);
                $created->push($schedule);
            }

            $this->announceScheduleChange($scheduleClass, $userIds, $actorId);

            return $created;
        });
    }

    /**
     * A pilot is announced as itself; a tug master as the tug they are linked to, and not at all without one (CT-10).
     *
     * @param  class-string<PilotSchedule|TugMasterSchedule>  $scheduleClass
     * @param  list<int>  $userIds  Each user once.
     */
    private function announceScheduleChange(string $scheduleClass, array $userIds, int $actorId): void
    {
        if ($scheduleClass === PilotSchedule::class) {
            foreach ($userIds as $userId) {
                ResourceChanged::dispatch(ResourceKind::Pilot, $userId, $actorId);
            }

            return;
        }

        $tugboatIds = TugMasterProfile::query()->whereKey($userIds)->whereNotNull('assigned_tugboat_id')->pluck('assigned_tugboat_id');

        foreach ($tugboatIds as $tugboatId) {
            ResourceChanged::dispatch(ResourceKind::Tug, $tugboatId, $actorId);
        }
    }
}
