<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\MasterData\Models\ImdgClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An IMDG class reference row.
 *
 * @mixin ImdgClass
 */
class ImdgClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
        ];
    }
}
