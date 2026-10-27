<?php

use Illuminate\Support\Facades\DB;

/*
 * `composer contracts:schema` writes a schema.sql the AI Team can build its test database from (CT-17 §5).
 */

beforeEach(function () {
    useSchemaDatabase();
});

test('schema.sql carries the header, the full structure and the worker roles, and rebuilds the database', function () {
    $output = tempnam(sys_get_temp_dir(), 'schema');
    $rebuilt = 'atlantech_schema_rebuild';

    try {
        $this->artisan('atlantech:contracts-schema', ['--output' => $output, '--database' => 'atlantech_schema_test'])
            ->assertSuccessful();

        $schema = file_get_contents($output);

        expect($schema)
            ->toStartWith('-- ATLANTECH port database schema (CT-17 v1.0).')
            ->toContain('-- Last migration: 2026_10_28_200000_add_deferred_foreign_keys')
            ->toContain('CREATE TABLE `yard_runs`')
            ->toContain('CONSTRAINT `chk_br_ports` CHECK')
            ->toContain('GENERATED ALWAYS AS')
            ->toContain("CREATE ROLE IF NOT EXISTS 'r_yard';")
            ->not->toMatch('/AUTO_INCREMENT=\d+/');

        $structureOnly = strstr($schema, '-- Worker roles', true);
        DB::statement("CREATE DATABASE `{$rebuilt}`");
        DB::unprepared("USE `{$rebuilt}`;\n".$structureOnly);

        expect(DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', $rebuilt)->count())->toBe(100)
            ->and(DB::table('information_schema.CHECK_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', $rebuilt)->count())->toBe(129);
    } finally {
        DB::statement("DROP DATABASE IF EXISTS `{$rebuilt}`");
        DB::purge('mysql');
        @unlink($output);
    }
})->group('schema');

test('the generator stops when the output folder does not exist', function () {
    $this->artisan('atlantech:contracts-schema', ['--output' => sys_get_temp_dir().'/no-such-folder/schema.sql'])
        ->expectsOutputToContain('Folder not found')
        ->assertFailed();
})->group('schema');
