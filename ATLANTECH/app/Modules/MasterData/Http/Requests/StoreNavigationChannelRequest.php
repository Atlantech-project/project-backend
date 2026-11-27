<?php

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/navigation-channels` (P2-F1, FR-MD-02). The port staff add as many channels as the port has;
 * `is_active` defaults to true.
 */
class StoreNavigationChannelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', Rule::unique('navigation_channels', 'name')],
            ...NavigationChannelRules::measurements(required: true),
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
