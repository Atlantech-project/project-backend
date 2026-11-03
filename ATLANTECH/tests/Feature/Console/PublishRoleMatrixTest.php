<?php

use App\Modules\Access\Enums\Ability;
use App\Modules\Access\Enums\Role;

/*
 * `composer contracts:role-matrix` generates PortHub contracts/role-matrix.md from the role-to-ability matrix (P2-D1).
 */

beforeEach(function () {
    $this->output = tempnam(sys_get_temp_dir(), 'rolematrix');
});

afterEach(function () {
    @unlink($this->output);
});

it('lists every ability as a row and every role as a column', function () {
    $this->artisan('atlantech:contracts-role-matrix', ['--output' => $this->output])->assertSuccessful();

    $document = file_get_contents($this->output);

    foreach (Ability::cases() as $ability) {
        expect($document)->toContain("| `{$ability->value}` |");
    }

    foreach (Role::cases() as $role) {
        expect($document)->toContain("`{$role->value}`");
    }
});

it('marks the roles that hold an ability and says the marine safety officer holds none', function () {
    $this->artisan('atlantech:contracts-role-matrix', ['--output' => $this->output])->assertSuccessful();

    $document = file_get_contents($this->output);

    expect($document)->toContain('- `MARINE_SAFETY_OFFICER`: none')
        ->and($document)->toContain('- `MARINE_PILOT`: `tasks.own.read`, `tasks.own.update`')
        ->and($document)->toContain('- `PORT_GM`: `port-state.read`, `users.manage`');
});

it('writes the same file every time', function () {
    $this->artisan('atlantech:contracts-role-matrix', ['--output' => $this->output])->assertSuccessful();
    $first = file_get_contents($this->output);

    $this->artisan('atlantech:contracts-role-matrix', ['--output' => $this->output])->assertSuccessful();

    expect(file_get_contents($this->output))->toBe($first);
});

it('fails when the output folder does not exist', function () {
    $this->artisan('atlantech:contracts-role-matrix', ['--output' => sys_get_temp_dir().'/no-such-folder/role-matrix.md'])->assertFailed();
});
