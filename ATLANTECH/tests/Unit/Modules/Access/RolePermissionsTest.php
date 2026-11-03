<?php

use App\Modules\Access\Enums\Ability;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Services\RolePermissions;

/**
 * The approved role matrix (P2-D1), written out in full: one row per ability, the roles that hold it.
 * A change to `RolePermissions` must change this table, so it shows in the diff.
 *
 * @return array<string, list<string>>
 */
function expectedRoleMatrix(): array
{
    $office = ['PORT_GM', 'PORT_STAFF', 'PILOTAGE_MANAGER', 'PILOTAGE_OFFICER', 'TUG_MANAGER', 'TUG_OFFICER', 'TERMINAL_ADMIN', 'TERMINAL_OFFICER'];

    return [
        'port-state.read' => $office,
        'users.manage' => ['PORT_GM'],
        'devices.manage' => ['PORT_GM'],
        'audit.read' => ['PORT_GM'],
        'license.manage' => ['PORT_GM'],
        'ai-model.review' => ['PORT_GM'],
        'plans.safety-exception' => ['PORT_GM'],
        'port-setup.manage' => ['PORT_GM'],
        'pilot-roster.manage' => ['PILOTAGE_MANAGER'],
        'pilotage.assign' => ['PILOTAGE_OFFICER'],
        'tug-fleet.manage' => ['TUG_MANAGER'],
        'tug.assign' => ['TUG_OFFICER'],
        'berthing-requests.manage' => ['PORT_STAFF'],
        'baplie.manage' => ['PORT_STAFF'],
        'entry-window.decide' => ['PORT_STAFF'],
        'environment.manual-entry' => ['PORT_STAFF'],
        'recommendations.review' => ['PORT_STAFF'],
        'plans.confirm' => ['PORT_STAFF'],
        'plans.cancel' => ['PORT_STAFF'],
        'deviations.review' => ['PORT_STAFF'],
        'equipment.manage' => ['TERMINAL_ADMIN'],
        'yard.manage' => ['TERMINAL_ADMIN'],
        'yard-recommendations.decide' => ['TERMINAL_OFFICER'],
        'tos-export.record' => ['TERMINAL_OFFICER'],
        'tasks.own.read' => ['MARINE_PILOT', 'TUG_MASTER', 'TERMINAL_SUPERVISOR'],
        'tasks.own.update' => ['MARINE_PILOT', 'TUG_MASTER', 'TERMINAL_SUPERVISOR'],
        'terminal-status.update' => ['TERMINAL_SUPERVISOR'],
    ];
}

it('lists every ability in the expected table and nothing else', function () {
    expect(array_keys(expectedRoleMatrix()))->toEqualCanonicalizing(array_map(fn (Ability $a) => $a->value, Ability::cases()))
        ->and(Ability::cases())->toHaveCount(27)
        ->and(Role::cases())->toHaveCount(12);
});

it('gives each role exactly the expected abilities (12 roles x every ability)', function () {
    foreach (Role::cases() as $role) {
        foreach (Ability::cases() as $ability) {
            $expected = in_array($role->value, expectedRoleMatrix()[$ability->value], true);

            expect(RolePermissions::allows($role, $ability))
                ->toBe($expected, "{$role->value} / {$ability->value}");
        }
    }
});

it('lists abilities for a role without repeats and in step with allows()', function () {
    foreach (Role::cases() as $role) {
        $abilities = RolePermissions::abilitiesFor($role);

        expect($abilities)->toBe(array_values(array_unique($abilities, SORT_REGULAR)));
        expect(count($abilities))->toBe(
            count(array_filter(Ability::cases(), fn (Ability $a) => RolePermissions::allows($role, $a))),
        );
    }
});

it('grants every ability to at least one role', function () {
    foreach (Ability::cases() as $ability) {
        expect(expectedRoleMatrix()[$ability->value])->not->toBeEmpty($ability->value);
    }
});

it('gives the marine safety officer no ability until the Non-Container work starts', function () {
    expect(RolePermissions::abilitiesFor(Role::MarineSafetyOfficer))->toBe([]);
});

it('keeps field roles to own-task abilities, plus terminal status for the supervisor', function () {
    $ownTask = [Ability::TasksOwnRead, Ability::TasksOwnUpdate];

    expect(RolePermissions::abilitiesFor(Role::MarinePilot))->toEqual($ownTask)
        ->and(RolePermissions::abilitiesFor(Role::TugMaster))->toEqual($ownTask)
        ->and(RolePermissions::abilitiesFor(Role::TerminalSupervisor))->toEqual([...$ownTask, Ability::TerminalStatusUpdate]);
});

it('marks the three terminal roles as terminal scoped and the three field roles as field roles', function () {
    $terminal = array_filter(Role::cases(), fn (Role $r) => $r->isTerminalScoped());
    $field = array_filter(Role::cases(), fn (Role $r) => $r->isFieldRole());

    expect(array_map(fn (Role $r) => $r->value, array_values($terminal)))
        ->toBe(['TERMINAL_ADMIN', 'TERMINAL_OFFICER', 'TERMINAL_SUPERVISOR'])
        ->and(array_map(fn (Role $r) => $r->value, array_values($field)))
        ->toBe(['MARINE_PILOT', 'TUG_MASTER', 'TERMINAL_SUPERVISOR']);
});
