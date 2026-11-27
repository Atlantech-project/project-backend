<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string of `GET /api/v1/navigation-channels` (P2-F1): page and `is_active`.
 */
class NavigationChannelIndexRequest extends FormRequest
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
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
