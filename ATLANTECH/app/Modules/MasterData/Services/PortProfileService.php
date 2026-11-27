<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\PortProfile;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;

/**
 * Editing the port profile (P2-F1, FR-MD-01): under the row lock and the version token, with its audit row.
 */
class PortProfileService
{
    private const array OWNED_FIELDS = ['name', 'latitude', 'longitude', 'timezone', 'boundary_coordinates'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{name?: string, latitude?: float|string, longitude?: float|string, timezone?: string, boundary_coordinates?: array<string, mixed>|null}  $data
     */
    public function update(PortProfile $profile, array $data, string $version, int $actorId): PortProfile
    {
        return $this->audit->transaction(function () use ($profile, $data, $version, $actorId): PortProfile {
            $locked = VersionToken::lockAndAssert($profile, $version);

            $locked->fill(Arr::only($data, self::OWNED_FIELDS));
            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
    }
}
