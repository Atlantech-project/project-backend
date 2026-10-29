<?php

namespace App\Modules\Shared\Http\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Base for every list response: `{ "data": [...], "meta": { "page", "per_page", "total" } }` and nothing else,
 * no `links` and no Laravel paginator fields (CT-01; P1-A2 question 3).
 *
 * Extend it and set `$collects` to the item resource, or use it as is to return the items' own arrays.
 */
class PaginatedCollection extends ResourceCollection
{
    public function __construct(LengthAwarePaginator $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @param  array<string, mixed>  $paginated  The paginator as an array
     * @param  array<string, mixed>  $default  Laravel's `links` and `meta`, dropped
     * @return array{meta: array{page: int, per_page: int, total: int}}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => [
                'page' => (int) $paginated['current_page'],
                'per_page' => (int) $paginated['per_page'],
                'total' => (int) $paginated['total'],
            ],
        ];
    }
}
