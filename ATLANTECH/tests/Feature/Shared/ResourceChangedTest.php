<?php

use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Events\ResourceChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/*
 * SHARED-01: `ResourceChanged` reaches its listeners only after the write transaction commits. Every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    useTestingTransactionManager();
    DB::beginTransaction();

    $this->received = collect();
    Event::listen(ResourceChanged::class, fn (ResourceChanged $event) => $this->received->push($event));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('reaches listeners only after the transaction commits, with the kind, id and actor', function () {
    app(AuditService::class)->transaction(function () {
        ResourceChanged::dispatch(ResourceKind::Berth, 7, 3);

        expect($this->received)->toBeEmpty();
    });

    expect($this->received)->toHaveCount(1)
        ->and($this->received->first()->kind)->toBe(ResourceKind::Berth)
        ->and($this->received->first()->resourceId)->toBe(7)
        ->and($this->received->first()->actorId)->toBe(3);
});

it('waits for the outermost commit when transactions are nested', function () {
    app(AuditService::class)->transaction(function () {
        DB::transaction(fn () => ResourceChanged::dispatch(ResourceKind::Pilot, 12, null));

        expect($this->received)->toBeEmpty();
    });

    expect($this->received)->toHaveCount(1);
});

it('is dropped when the transaction rolls back', function () {
    expect(fn () => app(AuditService::class)->transaction(function () {
        ResourceChanged::dispatch(ResourceKind::Tug, 4, 3);

        throw new RuntimeException('The write failed.');
    }))->toThrow(RuntimeException::class, 'The write failed.');

    expect($this->received)->toBeEmpty();
});

it('reaches listeners at once outside a transaction', function () {
    ResourceChanged::dispatch(ResourceKind::Equipment, 5, null);

    expect($this->received)->toHaveCount(1);
});
