<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/*
 * These tests reach the real MySQL and Redis services from docker-compose.yml
 * (start them with `docker compose up -d`). Run only them with `--group=infra`.
 */

test('the configured MySQL server answers a query', function () {
    // phpunit.xml sets DB_DATABASE=:memory: for SQLite, which also reaches this connection;
    // point it back at the database docker-compose.yml creates.
    config(['database.connections.mysql.database' => 'atlantech']);

    expect(DB::connection('mysql')->scalar('select 1'))->toEqual(1);
})->group('infra');

test('the configured Redis server stores and returns a value', function () {
    $redis = Redis::connection();
    $key = 'infra-check:'.uniqid();

    $redis->set($key, 'ok', 'EX', 10);

    expect($redis->get($key))->toBe('ok');

    $redis->del($key);
})->group('infra');
