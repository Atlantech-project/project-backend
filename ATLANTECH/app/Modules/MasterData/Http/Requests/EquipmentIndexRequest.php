<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query of `GET /api/v1/terminals/{terminal}/equipment` (P2-F4).
 */
class EquipmentIndexRequest extends FormRequest
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
            'equipment_category_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
