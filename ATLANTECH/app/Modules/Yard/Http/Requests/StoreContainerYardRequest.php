<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\MasterData\Models\Terminal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of POST /api/v1/terminals/{terminal}/yards (P2-F4). The code is unique per terminal.
 */
class StoreContainerYardRequest extends FormRequest
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
        $terminal = $this->route('terminal');

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('storage_facilities', 'code')->where('terminal_id', $terminal instanceof Terminal ? $terminal->id : null),
            ],
            'name' => ['required', 'string', 'max:100'],
            ...ContainerYardRules::optionalFields(),
        ];
    }
}
