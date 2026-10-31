<?php

/*
 * `composer contracts:status-vocabulary` generates PortHub contracts/status-vocabulary.json from the enums (CT-21).
 */

beforeEach(function () {
    $this->output = tempnam(sys_get_temp_dir(), 'vocabulary');
});

afterEach(function () {
    @unlink($this->output);
});

it('lists each column with the values of its enum', function () {
    $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertSuccessful();

    $vocabulary = json_decode(file_get_contents($this->output), true);

    expect($vocabulary['columns']['notification_logs.status'])->toBe(['SENT', 'FAILED', 'ACKNOWLEDGED'])
        ->and($vocabulary['columns']['berthing_requests.status'])->toContain('SUBMITTED', 'PLAN_CONFIRMED')
        ->and($vocabulary['columns']['recommendations.status'])->toContain('INVALIDATED');
});

it('lists the plan component status as derived, not as a column', function () {
    $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertSuccessful();

    $vocabulary = json_decode(file_get_contents($this->output), true);

    expect($vocabulary['derived']['plan_components.derived_status'])->toBe(['UNASSIGNED', 'ASSIGNED', 'STARTED', 'DONE'])
        ->and($vocabulary['columns'])->not->toHaveKeys(['plan_components.status', 'plan_components.derived_status', 'actual_status_updates']);
});

it('writes the same bytes on every run', function () {
    $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertSuccessful();
    $first = file_get_contents($this->output);

    $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertSuccessful();

    expect(file_get_contents($this->output))->toBe($first);
});

it('refuses when the contracts folder is missing', function () {
    $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'atlantech-missing-'.uniqid().DIRECTORY_SEPARATOR.'status-vocabulary.json';

    $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $missing])->assertFailed();

    expect(file_exists($missing))->toBeFalse();
});
