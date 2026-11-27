<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Body of PUT /api/v1/terminals/{terminal}/yard-blocks/{yardBlock}/layout (P2-F4, DEC-29 G-03): the size of a regular block.
 */
class YardBlockLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public const int MAX_SLOTS = 5000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bays' => ['required', 'integer', 'min:1', 'max:200'],
            'rows' => ['required', 'integer', 'min:1', 'max:200'],
            'tiers' => ['required', 'integer', 'min:1', 'max:20'],
            'version' => VersionToken::rules(),
        ];
    }

    /**
     * @return list<Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['bays', 'rows', 'tiers'])) {
                return;
            }

            $data = $validator->getData();

            if ((int) $data['bays'] * (int) $data['rows'] * (int) $data['tiers'] > self::MAX_SLOTS) {
                $validator->errors()->add('bays', 'errors.yard.layout_too_large');
            }
        }];
    }
}
