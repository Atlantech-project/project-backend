<?php

namespace App\Modules\Access;

use App\Modules\Access\Enums\Ability;
use App\Modules\Access\Http\Middleware\EnsureAbility;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\RolePermissions;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Access: users, roles, devices and terminal scope (P1, P2).
 */
class AccessServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->aliasMiddleware('ability', EnsureAbility::class);

        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, function (User $user) use ($ability): bool {
                $role = $user->accessScope()->role;

                return $role !== null && RolePermissions::allows($role, $ability);
            });
        }
    }
}
