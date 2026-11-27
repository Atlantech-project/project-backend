<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Access\Enums\AccessRefusalReason;
use App\Modules\Access\Services\AccessRefusal;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Enums\TerminalType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A write of a terminal's productivity profile (P2-F6). Only container terminals have them (FR-MD-13, P-48: the CT
 * Administrator); the terminal type rule belongs to the endpoint (P2-D1). It is checked in `authorize()`, so a refused
 * terminal answers 403, audited like other role refusals, before the body is validated.
 */
abstract class ProductivityProfileWriteRequest extends FormRequest
{
    public function authorize(AccessRefusal $refusal): bool
    {
        $terminal = $this->route('terminal');

        if ($terminal instanceof Terminal && $terminal->terminal_type !== TerminalType::Container) {
            $refusal->refuse($this, $this->user(), AccessRefusalReason::RoleNotAllowed);
        }

        return true;
    }
}
