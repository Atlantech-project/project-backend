<?php

use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

enum ValidationProblemTestShade: string
{
    case Light = 'LIGHT';
}

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/_validation')->group(function () {
        Route::post('rules', fn (Request $request) => $request->validate([
            'name' => ['required', 'string', 'max:5'],
            'shade' => [Rule::enum(ValidationProblemTestShade::class)],
            'note' => [fn (string $attribute, mixed $value, Closure $fail) => $fail('Not allowed.')],
        ]));
        Route::post('with-messages', fn () => throw ValidationException::withMessages([
            'berth' => ['errors.validation_failed', 'The berth is busy.'],
        ]));
    });
});

it('returns 422 VALIDATION_FAILED with errors keyed by field and message keys', function () {
    $code = ErrorCode::ValidationFailed;

    $this->postJson('/api/v1/_validation/rules', ['name' => 'too long name', 'shade' => 'DARK', 'note' => 'x'])
        ->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertExactJson([
            'type' => $code->type(),
            'title' => $code->title(),
            'status' => 422,
            'code' => 'VALIDATION_FAILED',
            'message_key' => 'errors.validation_failed',
            'detail' => __('validation.max.string', ['attribute' => 'name', 'max' => 5]),
            'errors' => [
                'name' => ['validation.max'],
                'shade' => ['validation.enum'],
                'note' => ['errors.validation_failed'],
            ],
        ]);
});

it('lists every failed rule once', function () {
    $this->postJson('/api/v1/_validation/rules', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name', ['validation.required']);
});

it('keeps translation keys from withMessages and replaces free text with the generic key', function () {
    $this->postJson('/api/v1/_validation/with-messages')
        ->assertStatus(422)
        ->assertJsonPath('errors.berth', ['errors.validation_failed'])
        ->assertJsonPath('detail', __('errors.validation_failed'));
});
