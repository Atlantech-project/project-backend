<?php

namespace App\Modules\Access\Models;

use Database\Factories\Access\UserFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A port user. Signs in with `username` (DEC-35: no email column) and holds one active role at a time (DEC-36).
 * `updated_at` is the version token for the 409 check, so dates are written with microseconds.
 */
#[Table(name: 'users')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['name', 'username', 'password_hash', 'phone', 'is_active', 'must_change_password'])]
#[Hidden(['password_hash'])]
#[UseFactory(UserFactory::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The column that holds the password hash.
     *
     * @var string
     */
    protected $authPasswordName = 'password_hash';

    /**
     * There is no remember-token column; web sign-in uses the Sanctum session (P1-C1).
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * @return HasMany<UserRoleAssignment, $this>
     */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    /**
     * The one assignment with no end date (DEC-36).
     *
     * @return HasOne<UserRoleAssignment, $this>
     */
    public function activeRoleAssignment(): HasOne
    {
        return $this->hasOne(UserRoleAssignment::class)->whereNull('active_to');
    }
}
