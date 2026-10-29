<?php

use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Symfony\Component\Yaml\Yaml;

/*
 * openapi/port-api.yaml (CT-02) must match what the server really does (P1-A4): every /api/v1 route is in `paths`
 * and the reverse, and the shared CT-01 parts equal the code they describe. A PR that changes an endpoint or an
 * error code without the file fails here.
 */

/**
 * @return array<string, mixed>
 */
function openApi(): array
{
    return Yaml::parseFile(base_path('openapi/port-api.yaml'));
}

/**
 * "GET /api/v1/things/{thing}" for every operation in the file.
 *
 * @return list<string>
 */
function openApiOperations(array $spec): array
{
    $operations = [];

    foreach ($spec['paths'] ?? [] as $path => $item) {
        foreach (array_keys($item) as $method) {
            if (in_array($method, ['get', 'put', 'post', 'delete', 'patch', 'options'], true)) {
                $operations[] = strtoupper($method).' '.$path;
            }
        }
    }

    sort($operations);

    return $operations;
}

/**
 * The same for every route the application serves under /api/v1 (HEAD comes with GET and is not listed).
 *
 * @return list<string>
 */
function apiRouteOperations(): array
{
    $operations = [];

    /** @var Route $route */
    foreach (Router::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1')) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $operations[] = $method.' /'.$route->uri();
        }
    }

    sort($operations);

    return $operations;
}

it('is an OpenAPI 3.0 file with a semantic version', function () {
    $spec = openApi();

    expect($spec['openapi'])->toStartWith('3.0.')
        ->and($spec['info']['version'])->toMatch('/^\d+\.\d+\.\d+$/')
        ->and($spec)->toHaveKey('paths');
});

it('lists exactly the routes the server serves under /api/v1', function () {
    expect(openApiOperations(openApi()))->toBe(apiRouteOperations());
});

it('lists exactly the ErrorCode values, in order', function () {
    expect(openApi()['components']['schemas']['ErrorCode']['enum'])
        ->toBe(array_map(fn (ErrorCode $code) => $code->value, ErrorCode::cases()));
});

it('has one problem response per ErrorCode with the right status, code, key, title and type', function () {
    $responses = openApi()['components']['responses'];

    foreach (ErrorCode::cases() as $code) {
        expect($responses)->toHaveKey($code->name);

        $example = $responses[$code->name]['content']['application/problem+json']['example'];

        expect($example)
            ->status->toBe($code->status())
            ->code->toBe($code->value)
            ->message_key->toBe($code->messageKey())
            ->title->toBe($code->title())
            ->type->toBe($code->type());
    }

    expect(array_keys($responses))->toEqualCanonicalizing(array_map(fn (ErrorCode $code) => $code->name, ErrorCode::cases()));
});

it('describes the pagination bounds PageRequest enforces', function () {
    $parameters = openApi()['components']['parameters'];

    expect($parameters['Page']['schema'])->toMatchArray(['minimum' => 1, 'maximum' => PageRequest::MAX_PAGE, 'default' => 1])
        ->and($parameters['PerPage']['schema'])->toMatchArray([
            'minimum' => 1,
            'maximum' => PageRequest::MAX_PER_PAGE,
            'default' => PageRequest::DEFAULT_PER_PAGE,
        ])
        ->and(openApi()['components']['schemas']['PageMeta']['required'])->toBe(['page', 'per_page', 'total']);
});
