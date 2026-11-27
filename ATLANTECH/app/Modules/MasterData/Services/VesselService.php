<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Vessel;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Editing a vessel's particulars (P3-H7, FR-BQ-13, sequence 2.8). `call_sign` and `flag_state` are also filled by the
 * BAPLIE worker, so the Backend writes them only while they are empty (CT-15, P1-B5).
 */
class VesselService
{
    private const array ATTRIBUTES = ['name', 'vessel_type_id', 'loa', 'beam', 'max_draft', 'net_tonnage', 'moulded_depth'];

    private const array FILLED_ONCE = ['call_sign', 'flag_state'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data  Validated particulars; only the keys sent.
     *
     * @throws ValidationException 422 for a second value of `call_sign` or `flag_state`, or a beam not below the length.
     */
    public function update(Vessel $vessel, array $data, string $version, int $actorId): Vessel
    {
        return $this->audit->transaction(function () use ($vessel, $data, $version, $actorId): Vessel {
            $locked = VersionToken::lockAndAssert($vessel, $version);

            $this->fillOnce($locked, Arr::only($data, self::FILLED_ONCE));
            $locked->fill(Arr::only($data, self::ATTRIBUTES));
            $this->assertBeamBelowLength($locked);

            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked;
        });
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function fillOnce(Vessel $vessel, array $values): void
    {
        foreach ($values as $column => $value) {
            $stored = $vessel->getAttribute($column);

            if ($stored === $value) {
                continue;
            }

            if ($stored !== null) {
                throw ValidationException::withMessages([$column => __('errors.vessels.already_set')]);
            }

            $vessel->forceFill([$column => $value]);
        }
    }

    private function assertBeamBelowLength(Vessel $vessel): void
    {
        if ((float) $vessel->beam >= (float) $vessel->loa) {
            throw ValidationException::withMessages(['beam' => __('errors.vessels.beam_not_below_loa')]);
        }
    }
}
