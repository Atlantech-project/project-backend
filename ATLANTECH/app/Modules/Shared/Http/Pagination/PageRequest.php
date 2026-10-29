<?php

namespace App\Modules\Shared\Http\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The `?page=&per_page=` of a list endpoint (CT-01; P1-A2 question 3).
 *
 * `page` 1..1 000 000, `per_page` 1..100, default 25. A value out of range is refused with 422 VALIDATION_FAILED, never
 * clamped; a page past the end is a normal 200 with empty `data`. Pair with PaginatedCollection for the response.
 */
final readonly class PageRequest
{
    public const int DEFAULT_PER_PAGE = 25;

    public const int MAX_PER_PAGE = 100;

    /**
     * Far past any real list; keeps `(page - 1) * per_page` inside an integer offset.
     */
    public const int MAX_PAGE = 1_000_000;

    public function __construct(
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * The rules, for a FormRequest that validates more query parameters next to the page.
     *
     * @return array{page: list<string>, per_page: list<string>}
     */
    public static function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ];
    }

    /**
     * Read and validate the page from the query string.
     *
     * @throws ValidationException
     */
    public static function fromRequest(Request $request): self
    {
        $validated = Validator::make($request->query(), self::rules())->validate();

        return new self(
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE),
        );
    }

    /**
     * Run the query for this page. The query must carry its own ordering (stated per endpoint).
     *
     * @param  EloquentBuilder<*>|Relation<*, *, *>|QueryBuilder  $query
     */
    public function paginate(EloquentBuilder|Relation|QueryBuilder $query): LengthAwarePaginator
    {
        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }
}
