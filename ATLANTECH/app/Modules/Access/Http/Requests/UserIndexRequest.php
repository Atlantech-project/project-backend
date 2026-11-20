<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of `GET /api/v1/users` (P2-E1): page, `is_active` and the text search `q` (name or username).
 */
class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...PageRequest::rules(),
            'is_active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
