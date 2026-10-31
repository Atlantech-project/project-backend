<?php

use Illuminate\Support\Facades\DB;

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
        ->and($vocabulary['columns'])->not->toHaveKeys(['plan_components.status', 'plan_components.derived_status']);
});

it('lists exactly the enum columns of the database, with the same values', function () {
    useSchemaDatabase();

    $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertSuccessful();

    $vocabulary = json_decode(file_get_contents($this->output), true);

    $database = collect(DB::select(
        "select TABLE_NAME as table_name, COLUMN_NAME as column_name, COLUMN_TYPE as column_type
         from information_schema.COLUMNS
         where TABLE_SCHEMA = database() and DATA_TYPE = 'enum'"
    ))->mapWithKeys(fn (object $column): array => [
        "{$column->table_name}.{$column->column_name}" => str_getcsv(substr($column->column_type, 5, -1), ',', "'", ''),
    ])->sortKeys()->all();

    expect($vocabulary['columns'])->toBe($database);
});

it('refuses a column named by two enums', function () {
    $path = app_path('Modules/Shared/Enums/ZzDuplicateColumn.php');
    file_put_contents($path, "<?php\n\nnamespace App\\Modules\\Shared\\Enums;\n\n/**\n * `notification_logs.status`.\n */\nenum ZzDuplicateColumn: string\n{\n    case One = 'ONE';\n}\n");

    try {
        $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertFailed();
    } finally {
        unlink($path);
    }
});

it('refuses a file in an Enums folder that is not an enum', function () {
    $path = app_path('Modules/Shared/Enums/ZzNotAnEnum.php');
    file_put_contents($path, "<?php\n\nnamespace App\\Modules\\Shared\\Enums;\n\nclass ZzNotAnEnum {}\n");

    try {
        $this->artisan('atlantech:contracts-status-vocabulary', ['--output' => $this->output])->assertFailed();
    } finally {
        unlink($path);
    }
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
