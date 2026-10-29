<?php

use Illuminate\Support\Facades\File;

/*
 * `composer contracts:openapi` publishes the OpenAPI source to PortHub contracts/openapi (CT-02, DEC-13).
 */

beforeEach(function () {
    $this->contracts = sys_get_temp_dir().DIRECTORY_SEPARATOR.'atlantech-contracts-'.uniqid();
    mkdir($this->contracts);
});

afterEach(function () {
    File::deleteDirectory($this->contracts);
});

it('copies the file byte for byte, creating the openapi folder', function () {
    $output = $this->contracts.DIRECTORY_SEPARATOR.'openapi'.DIRECTORY_SEPARATOR.'port-api.yaml';

    $this->artisan('atlantech:contracts-openapi', ['--output' => $output])->assertSuccessful();

    expect(file_get_contents($output))->toBe(file_get_contents(base_path('openapi/port-api.yaml')));
});

it('refuses when the contracts folder is missing', function () {
    $output = $this->contracts.DIRECTORY_SEPARATOR.'missing'.DIRECTORY_SEPARATOR.'openapi'.DIRECTORY_SEPARATOR.'port-api.yaml';

    $this->artisan('atlantech:contracts-openapi', ['--output' => $output])->assertFailed();

    expect(file_exists($output))->toBeFalse();
});
