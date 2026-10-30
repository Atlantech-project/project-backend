<?php

use App\Modules\Access\Http\Middleware\EnsureAccountUsable;
use App\Modules\Access\Models\User;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * P1-C2 password gate and change-password (CT-03, FR-AC-03, FR-AC-10). Stateful requests from the web app's origin,
 * on the MySQL schema database; every test is rolled back. /_gate stands in for any other signed-in route.
 */

const GATE_ORIGIN = 'http://localhost:5173';
const GATE_PASSWORD = 'Current-pass1';

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);

    Route::middleware(['api', 'auth:web', EnsureAccountUsable::class])
        ->get('api/v1/_gate', fn () => ['ok' => true]);

    $this->withHeaders(['Origin' => GATE_ORIGIN, 'Referer' => GATE_ORIGIN.'/']);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

function gateUser(array $attributes = []): User
{
    return User::factory()->create(['password_hash' => GATE_PASSWORD, ...$attributes]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function changePassword(array $overrides = []): TestResponse
{
    return test()->postJson('/api/v1/auth/change-password', [
        'current_password' => GATE_PASSWORD,
        'new_password' => 'New-pass-22',
        'new_password_confirmation' => 'New-pass-22',
        ...$overrides,
    ]);
}

describe('the gate', function () {
    it('refuses other routes with 403 PASSWORD_CHANGE_REQUIRED while the flag is set', function () {
        $this->actingAs(gateUser(['must_change_password' => true]), 'web');

        $this->getJson('/api/v1/_gate')
            ->assertForbidden()
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED')
            ->assertJsonPath('message_key', 'errors.password_change_required');
    });

    it('still answers auth/me while the flag is set', function () {
        $this->actingAs(gateUser(['must_change_password' => true]), 'web');

        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('must_change_password', true);
    });

    it('lets a user without the flag through', function () {
        $this->actingAs(gateUser(), 'web');

        $this->getJson('/api/v1/_gate')->assertOk();
    });

    it('opens the other routes after the password is changed', function () {
        $this->actingAs(gateUser(['must_change_password' => true]), 'web');

        changePassword()->assertOk();

        $this->getJson('/api/v1/_gate')->assertOk();
    });
});

describe('a deactivated user', function () {
    it('is refused with 403 ACCOUNT_DISABLED on every signed-in route', function (string $path) {
        $this->actingAs(gateUser(['is_active' => false]), 'web');

        $this->getJson($path)->assertForbidden()->assertJsonPath('code', 'ACCOUNT_DISABLED');
    })->with(['/api/v1/_gate', '/api/v1/auth/me']);

    it('is signed out, so the next request is 401', function () {
        $user = gateUser();
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => GATE_PASSWORD])->assertOk();
        $user->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/auth/me')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    });
});

describe('POST auth/change-password', function () {
    it('saves the password, clears the flag and answers with the profile', function () {
        $user = gateUser(['must_change_password' => true]);
        $this->actingAs($user, 'web');

        changePassword()
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('must_change_password', false);

        $fresh = $user->fresh();
        expect(Hash::check('New-pass-22', $fresh->password_hash))->toBeTrue()
            ->and($fresh->must_change_password)->toBeFalse();
    });

    it('writes one audit row without the password', function () {
        $user = gateUser(['must_change_password' => true]);
        $this->actingAs($user, 'web');

        changePassword()->assertOk();

        $row = AuditLog::query()->where('entity_type', 'users')->where('entity_id', $user->id)->sole();
        expect($row->action)->toBe(AuditLogAction::Update)
            ->and($row->actor_id)->toBe($user->id)
            ->and($row->before_state)->toBe(['must_change_password' => true])
            ->and($row->after_state)->toEqual(['must_change_password' => false, 'password_changed' => true]);
    });

    it('gives a new session id', function () {
        $user = gateUser();
        $this->postJson('/api/v1/auth/login', ['username' => $user->username, 'password' => GATE_PASSWORD])->assertOk();
        $before = session()->getId();

        changePassword()->assertOk();

        expect(session()->getId())->not->toBe($before);
    });

    it('refuses a bad body with 422 and the rule key', function (array $overrides, string $field, string $key) {
        $user = gateUser(['must_change_password' => true]);
        $this->actingAs($user, 'web');

        changePassword($overrides)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonFragment([$field => [$key]]);

        expect($user->fresh()->must_change_password)->toBeTrue();
    })->with([
        'wrong current password' => [['current_password' => 'Wrong-pass1'], 'current_password', 'validation.current_password'],
        'no current password' => [['current_password' => null], 'current_password', 'validation.required'],
        'not confirmed' => [['new_password_confirmation' => 'Other-pass-22'], 'new_password', 'validation.confirmed'],
        'same as current' => [['new_password' => GATE_PASSWORD, 'new_password_confirmation' => GATE_PASSWORD], 'new_password', 'validation.different'],
        'too short or no numbers' => [['new_password' => 'abcdefg', 'new_password_confirmation' => 'abcdefg'], 'new_password', 'validation.password'],
    ]);

    it('answers 401 without a session', function () {
        changePassword()->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
    });

    it('throttles guessing with 429 and Retry-After', function () {
        $this->actingAs(gateUser(), 'web');

        foreach (range(1, 5) as $attempt) {
            changePassword(['current_password' => 'Wrong-pass1'])->assertUnprocessable();
        }

        changePassword(['current_password' => 'Wrong-pass1'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS')
            ->assertHeader('Retry-After');
    });
});
