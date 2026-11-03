<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Middleware\EnsureMinAppVersion;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\License\Http\Middleware\EnsureLicenseAllowsWrite;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Http\Middleware\EnsureRecordVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * P2-D2 write chain order (FR-GL-01): 401, password gate, ability, 426, 423, 409 version. The three hook steps pass
 * through until P1-C3, P2-D5 and P2-D4 fill them, so the tests bind refusing fakes in their place; a fake refuses when
 * the request header X-Fail names its status. Every test is rolled back.
 */

/**
 * Replace the three hook steps with fakes that refuse when `X-Fail` lists their status (426, 423 or 409).
 */
function fakeHookSteps(): void
{
    $fake = fn (string $parent, int $status, ErrorCode $code) => app()->bind($parent, fn () => new class($status, $code)
    {
        public function __construct(private int $status, private ErrorCode $code) {}

        public function handle(Request $request, Closure $next)
        {
            if (in_array((string) $this->status, explode(',', (string) $request->header('X-Fail')), true)) {
                throw new ApiProblemException($this->code);
            }

            return $next($request);
        }
    });

    $fake(EnsureMinAppVersion::class, 426, ErrorCode::AppUpdateRequired);
    $fake(EnsureLicenseAllowsWrite::class, 423, ErrorCode::LicenseReadOnly);
    $fake(EnsureRecordVersion::class, 409, ErrorCode::VersionConflict);
}

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);

    Route::middleware(['api', 'port.api', 'ability:users.manage', 'version.token'])
        ->prefix('api/v1')
        ->post('test/chain', fn () => response()->json(['ok' => true]));

    // Every step listed first-to-last the wrong way round: the priority list must still order them.
    Route::middleware(['api', 'version.token', 'ability:users.manage', 'port.api'])
        ->prefix('api/v1')
        ->post('test/chain-reversed', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'version.token', 'terminal.scope', 'app.version', 'ability:port-state.read', 'port.api'])
        ->prefix('api/v1')
        ->post('test/terminals/{terminal}', fn () => response()->json(['ok' => true]));

    Route::middleware(['api', 'port.api', 'ability:users.manage'])
        ->prefix('api/v1')
        ->get('test/users/{user}', fn (User $user) => response()->json(['id' => $user->id]));
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('passes a request through all hook steps while they are pass-through', function () {
    $user = makeUserWithRole(Role::PortGm);

    $this->actingAs($user, 'web')
        ->withHeaders(['X-App-Version' => '0.0.1'])
        ->postJson('/api/v1/test/chain')
        ->assertOk();
});

it('answers the first failing step of the chain', function (string $path) {
    fakeHookSteps();
    $allFail = ['X-Fail' => '426,423,409'];

    // 401 before everything
    $this->withHeaders($allFail)->postJson($path)->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');

    // password gate before the role check
    $this->actingAs(makeUserWithRole(Role::PortStaff, ['must_change_password' => true]), 'web')
        ->withHeaders($allFail)->postJson($path)
        ->assertForbidden()->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    // role before 426
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')
        ->withHeaders($allFail)->postJson($path)
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    $gm = makeUserWithRole(Role::PortGm);

    $this->actingAs($gm, 'web')->withHeaders($allFail)->postJson($path)
        ->assertStatus(426)->assertJsonPath('code', 'APP_UPDATE_REQUIRED');
    $this->actingAs($gm, 'web')->withHeaders(['X-Fail' => '423,409'])->postJson($path)
        ->assertStatus(423)->assertJsonPath('code', 'LICENSE_READ_ONLY');
    $this->actingAs($gm, 'web')->withHeaders(['X-Fail' => '409'])->postJson($path)
        ->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICT');
    $this->actingAs($gm, 'web')->withHeaders(['X-Fail' => ''])->postJson($path)->assertOk();
})->with(['/api/v1/test/chain', '/api/v1/test/chain-reversed']);

it('answers 403 before 404 for an unauthorised caller and 404 for an allowed one', function () {
    $this->actingAs(makeUserWithRole(Role::PortStaff), 'web')
        ->getJson('/api/v1/test/users/999999')
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    $this->actingAs(makeUserWithRole(Role::PortGm), 'web')
        ->getJson('/api/v1/test/users/999999')
        ->assertNotFound();
});

it('refuses another terminal with 403 before 426, 423 and 409, and before any 404', function () {
    fakeHookSteps();
    $own = OrganizationUnit::factory()->create();
    $other = OrganizationUnit::factory()->create();
    $user = makeUserWithRole(Role::TerminalOfficer, unit: $own);
    $allFail = ['X-Fail' => '426,423,409'];

    $this->actingAs($user, 'web')->withHeaders($allFail)->postJson("/api/v1/test/terminals/{$other->terminal_id}")
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->actingAs($user, 'web')->withHeaders($allFail)->postJson('/api/v1/test/terminals/999999')
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->actingAs($user, 'web')->withHeaders($allFail)->postJson("/api/v1/test/terminals/{$own->terminal_id}")
        ->assertStatus(426);
    $this->actingAs($user, 'web')->withHeaders(['X-Fail' => ''])->postJson("/api/v1/test/terminals/{$own->terminal_id}")
        ->assertOk();
});
