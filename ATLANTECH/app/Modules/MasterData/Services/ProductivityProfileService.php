<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Models\TerminalProductivityProfile;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\PeriodOverlap;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Creating and editing terminal productivity profiles (P2-F6, FR-MD-13). The AI picks the profile valid on a date: the
 * vessel type's first, then the terminal default, so two profiles of one terminal and the same vessel type (the default
 * apart) never share a day. A profile is never deleted: its period is closed with `valid_to`.
 */
class ProductivityProfileService
{
    private const array ATTRIBUTES = ['vessel_type_id', 'productivity_rate', 'rate_unit', 'fixed_overhead_minutes', 'valid_from', 'valid_to'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * The type is copied from the terminal (composite foreign key), never taken from the request.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException 422 `errors.productivity_profiles.overlap` or `.period_invalid`.
     */
    public function create(Terminal $terminal, array $data, int $actorId): TerminalProductivityProfile
    {
        return $this->audit->transaction(function () use ($terminal, $data, $actorId): TerminalProductivityProfile {
            $this->lockTerminal($terminal);

            $profile = new TerminalProductivityProfile([
                ...Arr::only($data, self::ATTRIBUTES),
                'terminal_id' => $terminal->id,
                'terminal_type' => $terminal->terminal_type,
            ]);
            $this->assertValidPeriod($profile);

            $profile->save();
            $profile->refresh();
            $this->audit->recordCreated($profile, $actorId);

            return $profile;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException 422 `errors.productivity_profiles.overlap` or `.period_invalid`.
     */
    public function update(Terminal $terminal, TerminalProductivityProfile $profile, array $data, string $version, int $actorId): TerminalProductivityProfile
    {
        return $this->audit->transaction(function () use ($terminal, $profile, $data, $version, $actorId): TerminalProductivityProfile {
            $this->lockTerminal($terminal);
            $locked = VersionToken::lockAndAssert($profile, $version);

            $locked->fill(Arr::only($data, self::ATTRIBUTES));

            if ($locked->isDirty(['vessel_type_id', 'valid_from', 'valid_to'])) {
                $this->assertValidPeriod($locked);
            }

            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
    }

    /**
     * Every write of the terminal's profiles waits here, so two requests cannot both pass the overlap check.
     */
    private function lockTerminal(Terminal $terminal): void
    {
        Terminal::query()->whereKey($terminal->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * `valid_from` and `valid_to` are whole days, both included; the same edge day is an overlap (SHARED-01).
     *
     * @throws ValidationException
     */
    private function assertValidPeriod(TerminalProductivityProfile $profile): void
    {
        if ($profile->valid_to !== null && $profile->valid_to->lt($profile->valid_from)) {
            throw ValidationException::withMessages(['valid_to' => 'errors.productivity_profiles.period_invalid']);
        }

        $others = TerminalProductivityProfile::query()
            ->where('terminal_id', $profile->terminal_id)
            ->where('vessel_type_id', $profile->vessel_type_id)
            ->when($profile->exists, fn ($query) => $query->whereKeyNot($profile->id))
            ->get();

        foreach ($others as $other) {
            if (PeriodOverlap::dates($profile->valid_from, $profile->valid_to, $other->valid_from, $other->valid_to)) {
                throw ValidationException::withMessages(['valid_from' => 'errors.productivity_profiles.overlap']);
            }
        }
    }
}
