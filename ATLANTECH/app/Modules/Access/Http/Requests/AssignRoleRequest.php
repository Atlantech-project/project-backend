<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\Role;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/users/{user}/roles` (P2-E2, FR-AC-11): a terminal role needs `terminal_id`, any other role refuses it.
 */
class AssignRoleRequest extends FormRequest
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
        $role = $this->input('role');
        $terminalScoped = is_string($role) && (Role::tryFrom($role)?->isTerminalScoped() ?? false);

        return [
            'role' => ['required', 'string', Rule::enum(Role::class)],
            'terminal_id' => $terminalScoped ? ['required', 'integer', 'min:1'] : ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
