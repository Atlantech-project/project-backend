<?php

use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * P2-D3 audit service (FR-GL-02, NFR-SEC-6). Transactions are the subject here, so these tests commit for real on the
 * MySQL schema database and remove what they wrote.
 */

beforeEach(function () {
    useSchemaDatabase();
    cleanAudit();
    $this->audit = app(AuditService::class);
});

afterEach(function () {
    cleanAudit();
    DB::disconnect();
});

function cleanAudit(): void
{
    DB::table('audit_logs')->delete();
    DB::table('users')->where('username', 'like', 'audit-test-%')->delete();
}

function auditTestUser(array $attributes = []): User
{
    return User::factory()->create(['username' => 'audit-test-'.fake()->unique()->numerify('######'), ...$attributes]);
}

it('saves the change and its audit row together', function () {
    $actor = auditTestUser();

    $user = $this->audit->transaction(function () use ($actor) {
        $user = auditTestUser();
        $this->audit->recordCreated($user, $actor->id);

        return $user;
    });

    $row = AuditLog::query()->where('entity_id', $user->id)->sole();

    expect($row->action)->toBe(AuditLogAction::Create)
        ->and($row->entity_type)->toBe('users')
        ->and($row->actor_id)->toBe($actor->id)
        ->and($row->before_state)->toBeNull()
        ->and($row->after_state)->toHaveKey('username', $user->username)
        ->and($row->after_state)->not->toHaveKey('password_hash');
});

it('stores neither the change nor the audit row when the write fails', function () {
    $username = 'audit-test-rollback';

    expect(fn () => $this->audit->transaction(function () use ($username) {
        $user = auditTestUser(['username' => $username]);
        $this->audit->recordCreated($user, null);

        throw new RuntimeException('write failed');
    }))->toThrow(RuntimeException::class);

    expect(User::query()->where('username', $username)->exists())->toBeFalse()
        ->and(AuditLog::query()->count())->toBe(0);
});

it('stores neither when the audit row cannot be saved', function () {
    $username = 'audit-test-bad-audit';

    expect(fn () => $this->audit->transaction(function () use ($username) {
        $user = auditTestUser(['username' => $username]);
        // CREATE with a before state breaks chk_al_states
        $this->audit->record(AuditLogAction::Create, 'users', $user->id, null, ['x' => 1], null);
    }))->toThrow(QueryException::class);

    expect(User::query()->where('username', $username)->exists())->toBeFalse();
});

it('refuses to write an audit row outside a transaction', function () {
    expect(fn () => $this->audit->record(AuditLogAction::Update, 'users', 1, null, null, null))
        ->toThrow(LogicException::class)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('writes NULL actor for the system', function () {
    $this->audit->transaction(fn () => $this->audit->record(AuditLogAction::StatusChange, 'berthing_requests', 7, null, ['status' => 'A'], ['status' => 'B']));

    $row = AuditLog::query()->sole();

    expect($row->actor_id)->toBeNull()
        ->and($row->action)->toBe(AuditLogAction::StatusChange)
        ->and($row->entity_id)->toBe(7);
});

it('stores only the changed fields on update and nothing when nothing changed', function () {
    $actor = auditTestUser();
    $user = auditTestUser(['name' => 'Old', 'password_hash' => 'Secret-pass1']);

    $this->audit->transaction(function () use ($user, $actor) {
        $user->update(['name' => 'New', 'password_hash' => 'Other-pass22']);
        $this->audit->recordUpdated($user, $actor->id);
    });

    $row = AuditLog::query()->where('action', AuditLogAction::Update)->sole();

    expect(array_keys($row->before_state))->toBe(['name', 'updated_at'])
        ->and($row->before_state['name'])->toBe('Old')
        ->and($row->after_state['name'])->toBe('New')
        ->and($row->after_state)->not->toHaveKey('password_hash');

    $unchanged = $this->audit->transaction(function () use ($user, $actor) {
        $user = User::query()->findOrFail($user->id);
        $user->update(['name' => 'New']);

        return $this->audit->recordUpdated($user, $actor->id);
    });

    expect($unchanged)->toBeNull()
        ->and(AuditLog::query()->count())->toBe(1);
});

it('stores the old row on delete, without an after state', function () {
    $user = auditTestUser();

    $this->audit->transaction(function () use ($user) {
        $user->delete();
        $this->audit->recordDeleted($user, null);
    });

    $row = AuditLog::query()->where('action', AuditLogAction::Delete)->sole();

    expect($row->before_state)->toHaveKey('username', $user->username)
        ->and($row->after_state)->toBeNull();
});

it('keeps a refused attempt after the refused write was rolled back', function () {
    $actor = auditTestUser();

    try {
        $this->audit->transaction(function () {
            auditTestUser(['username' => 'audit-test-refused']);

            throw new RuntimeException('refused');
        });
    } catch (RuntimeException) {
        $this->audit->rejectedAttempt('POST /api/v1/berths', ErrorCode::LicenseReadOnly, 'license_read_only', $actor->id, $actor->username, 'berths');
    }

    $row = AuditLog::query()->sole();

    expect($row->action)->toBe(AuditLogAction::RejectedAttempt)
        ->and($row->entity_type)->toBe('berths')
        ->and($row->entity_id)->toBeNull()
        ->and($row->actor_id)->toBe($actor->id)
        ->and($row->after_state)->toEqual([
            'route' => 'POST /api/v1/berths',
            'status' => 423,
            'code' => 'LICENSE_READ_ONLY',
            'reason' => 'license_read_only',
            'username' => $actor->username,
        ])
        ->and(User::query()->where('username', 'audit-test-refused')->exists())->toBeFalse();
});

it('records a refusal before sign-in with no actor and no entity', function () {
    $this->audit->rejectedAttempt('POST /api/v1/auth/login', ErrorCode::Unauthenticated, 'invalid_credentials', null, 'nobody');

    $row = AuditLog::query()->sole();

    expect($row->actor_id)->toBeNull()
        ->and($row->entity_id)->toBeNull()
        ->and($row->after_state)->toHaveKey('username', 'nobody');
});
