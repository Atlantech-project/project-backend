<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of PUT /api/v1/terminals/{terminal}/yards/{containerYard} (P2-F4): a field that is not sent keeps its value.
 */
class UpdateContainerYardRequest extends FormRequest
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
        $yard = $this->route('containerYard');
        $yard = $yard instanceof StorageFacility ? $yard : null;

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:30',
                Rule::unique('storage_facilities', 'code')->where('terminal_id', $yard?->terminal_id)->ignore($yard?->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            ...ContainerYardRules::optionalFields(),
            'version' => VersionToken::rules(),
        ];
    }
}
