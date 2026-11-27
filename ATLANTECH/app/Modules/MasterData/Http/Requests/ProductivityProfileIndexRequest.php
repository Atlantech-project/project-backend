<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of `GET /api/v1/terminals/{terminal}/productivity-profiles` (P2-F6): the page.
 */
class ProductivityProfileIndexRequest extends FormRequest
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
        return PageRequest::rules();
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
