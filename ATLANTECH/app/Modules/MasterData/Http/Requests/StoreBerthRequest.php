<?php

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `POST /api/v1/berths` (P2-F2, FR-MD-04). The rules repeat the `berths` CHECKs (`chk_berth_dimensions`,
 * `chk_berth_loa_fits`, `chk_berth_lat`, `chk_berth_lon`) and column sizes, so a bad value is a 422 and never a database
 * error. At least one terminal (DEC-51 allows several). Active terminals and the depth datum are checked by `BerthService`.
 */
class StoreBerthRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:30', 'unique:berths,code'],
            'name' => ['nullable', 'string', 'max:80'],
            ...BerthRules::dimensions(required: true, loaWithinLength: true),
            'terminal_ids' => ['required', 'array', 'min:1'],
            'terminal_ids.*' => ['integer', 'distinct', 'exists:terminals,id'],
        ];
    }
}
