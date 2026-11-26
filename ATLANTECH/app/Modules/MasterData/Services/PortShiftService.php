<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\PortProfile;
use App\Modules\MasterData\Models\PortShift;
use App\Modules\MasterData\Models\TugMasterSchedule;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\PeriodOverlap;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Validation\ValidationException;

/**
 * The fixed port shifts of the settings screen (P2-F3, AIBD-016): active shifts never overlap (back-to-back is allowed,
 * a shift may end the next day), and a shift that schedules from today on use keeps its times and stays active (Q8).
 * Every write holds the port profile row as the lock for the whole shift set, so two writes cannot both pass the
 * overlap check, even when there is no active shift yet.
 */
class PortShiftService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{code: string, name: string, start_time: string, end_time: string, is_active?: bool}  $data  Times as `HH:MM`.
     *
     * @throws ValidationException 422 `errors.port_shifts.overlap` on `start_time`.
     */
    public function create(array $data, int $actorId): PortShift
    {
        return $this->audit->transaction(function () use ($data, $actorId): PortShift {
            $this->lockShiftSet();

            $shift = new PortShift([...$this->withSeconds($data), 'is_active' => $data['is_active'] ?? true]);
            $this->assertNoOverlap($shift);
            $shift->save();
            $this->audit->recordCreated($shift, $actorId);

            return $shift->refresh();
        });
    }

    /**
     * @param  array{code?: string, name?: string, start_time?: string, end_time?: string, is_active?: bool}  $data  Times as `HH:MM`.
     * @param  string  $route  Method and path of the request, for the audit row of a refusal.
     *
     * @throws ValidationException 422 `errors.port_shifts.overlap` on `start_time`.
     * @throws ApiProblemException 409 VERSION_CONFLICT, or CONFLICT `errors.port_shifts.in_use`.
     */
    public function update(PortShift $target, array $data, string $version, User $actor, string $route): PortShift
    {
        $outcome = $this->audit->transaction(function () use ($target, $data, $version, $actor): PortShift|string {
            $this->lockShiftSet();

            $shift = VersionToken::lockAndAssert($target, $version);
            $shift->fill($this->withSeconds($data));

            if (! $shift->isDirty()) {
                return $shift;
            }

            if ($this->changesWhoIsOnShift($shift) && $this->isScheduledFromToday($shift)) {
                return 'in_use';
            }

            $this->assertNoOverlap($shift);
            $shift->save();
            $this->audit->recordUpdated($shift, $actor->id);

            return $shift->refresh();
        });

        if (is_string($outcome)) {
            $this->audit->rejectedAttempt($route, ErrorCode::Conflict, $outcome, $actor->id, $actor->username, 'port_shifts', $target->id);

            throw ApiProblemException::conflict('errors.port_shifts.'.$outcome);
        }

        return $outcome;
    }

    private function lockShiftSet(): void
    {
        PortProfile::query()->whereKey(PortClock::PORT_PROFILE_ID)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSeconds(array $data): array
    {
        foreach (['start_time', 'end_time'] as $field) {
            if (isset($data[$field])) {
                $data[$field] .= ':00';
            }
        }

        return $data;
    }

    private function changesWhoIsOnShift(PortShift $shift): bool
    {
        return $shift->isDirty(['start_time', 'end_time']) || ($shift->getOriginal('is_active') && ! $shift->is_active);
    }

    private function isScheduledFromToday(PortShift $shift): bool
    {
        $today = PortClock::today();

        return PilotSchedule::query()->where('port_shift_id', $shift->id)->where('shift_date', '>=', $today)->exists()
            || TugMasterSchedule::query()->where('port_shift_id', $shift->id)->where('shift_date', '>=', $today)->exists();
    }

    /**
     * @throws ValidationException 422 `errors.port_shifts.overlap` on `start_time`.
     */
    private function assertNoOverlap(PortShift $shift): void
    {
        if (! $shift->is_active) {
            return;
        }

        $others = PortShift::query()
            ->where('is_active', true)
            ->when($shift->exists, fn ($query) => $query->whereKeyNot($shift->id))
            ->get();

        foreach ($others as $other) {
            if (PeriodOverlap::timesOfDay($shift->start_time, $shift->end_time, $other->start_time, $other->end_time)) {
                throw ValidationException::withMessages(['start_time' => ['errors.port_shifts.overlap']]);
            }
        }
    }
}
