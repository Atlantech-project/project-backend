<?php

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_problem')->group(function () {
        Route::get('unauthenticated', fn () => throw new AuthenticationException);
        Route::get('forbidden', fn () => throw new AuthorizationException('Policy said no'));
        Route::get('missing-model', fn () => throw (new ModelNotFoundException)->setModel('App\\Secret\\Model', [7]));
        Route::get('only-get', fn () => 'ok');
        Route::get('throttled', fn () => throw new ThrottleRequestsException(headers: ['Retry-After' => '30']));
        Route::get('down', fn () => throw new HttpException(503, 'maintenance internals'));
        Route::get('too-large', fn () => throw new HttpException(413, 'payload internals'));
        Route::get('csrf', fn () => throw new TokenMismatchException('CSRF token mismatch.'));
        Route::get('crash', fn () => throw new RuntimeException('SQLSTATE[42S02]: secret_table missing'));
        Route::get('stable', fn () => throw new ApiProblemException(ErrorCode::AccountDisabled));
        Route::get('own-response', fn () => throw new HttpResponseException(response('teapot', 418)));
    });
});

dataset('mapped statuses', [
    'AuthenticationException → 401' => ['unauthenticated', 401, 'UNAUTHENTICATED'],
    'AuthorizationException → 403' => ['forbidden', 403, 'FORBIDDEN'],
    'ModelNotFoundException → 404' => ['missing-model', 404, 'NOT_FOUND'],
    'unknown route → 404' => ['does-not-exist', 404, 'NOT_FOUND'],
    'ThrottleRequestsException → 429' => ['throttled', 429, 'TOO_MANY_ATTEMPTS'],
    'HttpException 503 → 503' => ['down', 503, 'SERVICE_UNAVAILABLE'],
    'TokenMismatchException → 419' => ['csrf', 419, 'SESSION_EXPIRED'],
    'other 4xx → 400' => ['too-large', 400, 'BAD_REQUEST'],
    'any other Throwable → 500' => ['crash', 500, 'SERVER_ERROR'],
    'ApiProblemException keeps its code' => ['stable', 403, 'ACCOUNT_DISABLED'],
]);

it('renders an RFC 9457 problem with a stable code', function (string $path, int $status, string $code) {
    $errorCode = ErrorCode::from($code);

    $this->getJson("/api/v1/_problem/{$path}")
        ->assertStatus($status)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertExactJson([
            'type' => $errorCode->type(),
            'title' => $errorCode->title(),
            'status' => $status,
            'code' => $code,
            'message_key' => $errorCode->messageKey(),
            'detail' => __($errorCode->messageKey()),
        ]);
})->with('mapped statuses');

it('renders the problem even when the client does not ask for JSON', function () {
    $this->get('/api/v1/_problem/unauthenticated')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

it('maps a wrong method to 405 METHOD_NOT_ALLOWED and keeps the Allow header', function () {
    $this->postJson('/api/v1/_problem/only-get')
        ->assertStatus(405)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('Allow', 'GET, HEAD')
        ->assertJsonPath('code', 'METHOD_NOT_ALLOWED');
});

it('keeps Retry-After on 429', function () {
    $this->getJson('/api/v1/_problem/throttled')->assertHeader('Retry-After', '30');
});

it('shows no internals on a 500 even with APP_DEBUG on', function () {
    config(['app.debug' => true]);

    $response = $this->getJson('/api/v1/_problem/crash')->assertStatus(500);

    expect($response->json())->not->toHaveKeys(['exception', 'file', 'line', 'trace', 'message'])
        ->and($response->getContent())->not->toContain('SQLSTATE')
        ->not->toContain('secret_table')
        ->not->toContain('RuntimeException');
});

it('never echoes the exception message or model class on 4xx', function (string $path, string $secret) {
    config(['app.debug' => true]);

    expect($this->getJson("/api/v1/_problem/{$path}")->getContent())->not->toContain($secret);
})->with([
    ['forbidden', 'Policy said no'],
    ['missing-model', 'Secret'],
    ['down', 'maintenance internals'],
    ['too-large', 'payload internals'],
]);

it('leaves a response the code already built untouched', function () {
    $this->getJson('/api/v1/_problem/own-response')->assertStatus(418)->assertSee('teapot');
});

it('leaves errors outside /api to Laravel', function () {
    $this->getJson('/no-such-web-page')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonMissingPath('code');
});
