<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\PortProfileFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton per deployment. GM is a user_role_assignment, not a column here.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'port_profiles', incrementing: false)]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['id', 'public_id', 'port_code', 'governorate_code', 'name', 'latitude', 'longitude', 'timezone', 'boundary_coordinates'])]
#[UseFactory(PortProfileFactory::class)]
class PortProfile extends Model
{
    /** @use HasFactory<PortProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'boundary_coordinates' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
