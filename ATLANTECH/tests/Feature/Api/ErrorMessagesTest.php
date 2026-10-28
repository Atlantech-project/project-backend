<?php

use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Support\Facades\Lang;

/*
 * lang/en/errors.php is the English text of every catalogue message key (CT-01; NFR-UX-4).
 * A new ErrorCode case without a translation fails here.
 */
test('every catalogue code has an English message for its message key', function (ErrorCode $code) {
    expect(Lang::has($code->messageKey(), 'en', false))->toBeTrue()
        ->and(__($code->messageKey(), locale: 'en'))->toBeString()->not->toBeEmpty()->toEndWith('.');
})->with(ErrorCode::cases());

test('every flat message in errors.php belongs to a catalogue code', function () {
    $flatKeys = collect(Lang::get('errors', locale: 'en'))
        ->reject(fn (mixed $message) => is_array($message))
        ->keys()
        ->map(fn (string $key) => 'errors.'.$key)
        ->sort()
        ->values()
        ->all();

    $catalogueKeys = collect(ErrorCode::cases())
        ->map(fn (ErrorCode $code) => $code->messageKey())
        ->sort()
        ->values()
        ->all();

    expect($flatKeys)->toBe($catalogueKeys);
});

test('Laravel validation messages still resolve next to the application lang folder', function () {
    expect(Lang::has('validation.required', 'en', false))->toBeTrue();
});
