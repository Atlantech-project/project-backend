<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Models\YardBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of PUT /api/v1/terminals/{terminal}/yard-blocks/{yardBlock} (P2-F4): the code only; the size has its own endpoint.
 */
class UpdateYardBlockRequest extends FormRequest
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
        $block = $this->route('yardBlock');
        $block = $block instanceof YardBlock ? $block : null;

        return [
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('yard_blocks', 'code')->where('yard_zone_id', $block?->yard_zone_id)->ignore($block?->id),
            ],
            'yard_zone_id' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
