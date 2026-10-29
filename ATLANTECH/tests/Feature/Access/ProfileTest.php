<?php

use App\Modules\Access\Enums\Role;
use App\Modules\Access\Models\OrganizationUnit;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;

/*
 * P1-C1 GET /api/v1/auth/me (lead's answer N1): the profile again after a page reload, same shape as the
 * sign-in answer. Requests come from the web app's origin so Sanctum uses the session; every test is rolled back.
 */

beforeEach(function () {
    useSchemaDatabase();
    DB::beginTransaction();
    config(['sanctum.stateful' => ['localhost:5173']]);
    $this->withHeaders(['Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/']);
});

afterEach(function () {
    DB::rollBack();
    DB::disconnect();
});

it('returns the signed-in user profile, the same as the sign-in answer', function () {
    $unit = OrganizationUnit::factory()->create();
    $user = User::factory()->create(['username' => 'staff.ahmed', 'name' => 'Ahmed']);
    UserRoleAssignment::factory()->for($user)->for($unit)->create(['role' => Role::TerminalSupervisor]);

    $signIn = $this->postJson('/api/v1/auth/login', ['username' => 'staff.ahmed', 'password' => 'password'])->assertOk();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertExactJson($signIn->json())
        ->assertJsonPath('terminal_ids', [$unit->terminal_id]);
});

it('answers 401 UNAUTHENTICATED without a session', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});
