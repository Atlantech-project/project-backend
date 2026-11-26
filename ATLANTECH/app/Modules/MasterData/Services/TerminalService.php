<?php

namespace App\Modules\MasterData\Services;

use App\Modules\Access\Enums\UnitType;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Events\ResourceChanged;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;

/**
 * Creating, editing, deactivating and reactivating terminals (P2-F2, FR-MD-03, FR-MD-05). A terminal is created with its
 * TERMINAL organization unit; it is never deleted. Deactivation leaves its berths, roles and requests as they are (Q8).
 */
class TerminalService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{code: string, name: string, terminal_type: string, boundary_coordinates?: array<string, mixed>|null}  $data
     */
    public function create(array $data, int $actorId): Terminal
    {
        return $this->audit->transaction(function () use ($data, $actorId): Terminal {
            $terminal = Terminal::query()->create([
                'code' => $data['code'],
                'name' => $data['name'],
                'terminal_type' => $data['terminal_type'],
                'boundary_coordinates' => $data['boundary_coordinates'] ?? null,
                'is_active' => true,
            ]);
            $unit = $terminal->organizationUnit()->create(['unit_type' => UnitType::Terminal]);

            $this->audit->recordCreated($terminal, $actorId);
            $this->audit->recordCreated($unit, $actorId);

            return $terminal;
        });
    }

    /**
     * @param  array{name?: string, boundary_coordinates?: array<string, mixed>|null}  $data
     */
    public function update(Terminal $terminal, array $data, string $version, int $actorId): Terminal
    {
        return $this->audit->transaction(function () use ($terminal, $data, $version, $actorId): Terminal {
            $locked = VersionToken::lockAndAssert($terminal, $version);

            $locked->fill(Arr::only($data, ['name', 'boundary_coordinates']));
            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked;
        });
    }

    public function deactivate(Terminal $terminal, string $version, int $actorId): Terminal
    {
        return $this->setActive($terminal, false, $version, $actorId);
    }

    public function activate(Terminal $terminal, string $version, int $actorId): Terminal
    {
        return $this->setActive($terminal, true, $version, $actorId);
    }

    /**
     * Already in the asked state: a no-op (200, nothing written).
     */
    private function setActive(Terminal $terminal, bool $active, string $version, int $actorId): Terminal
    {
        return $this->audit->transaction(function () use ($terminal, $active, $version, $actorId): Terminal {
            $locked = VersionToken::lockAndAssert($terminal, $version);

            if ($locked->is_active === $active) {
                return $locked;
            }

            $locked->update(['is_active' => $active]);
            $this->audit->recordUpdated($locked, $actorId);

            foreach ($locked->berths()->pluck('berths.id') as $berthId) {
                ResourceChanged::dispatch(ResourceKind::Berth, (int) $berthId, $actorId);
            }

            return $locked;
        });
    }
}
