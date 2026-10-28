<?php

namespace App\Modules\License\Models;

use App\Modules\License\Enums\LicenseMode;
use App\Modules\License\Enums\LicenseStatus;
use App\Modules\License\Enums\PortStatus;
use App\Modules\License\Enums\ReadOnlyReason;
use Database\Factories\License\LicenseStateFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Local copy of the SaaS licence (hybrid signed token). Public key and API credential live in config, not here.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'license_states', incrementing: false)]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['id', 'token', 'license_ref', 'plan_name', 'max_terminals', 'max_berths', 'max_users', 'license_expires_at', 'token_expires_at', 'port_status', 'license_status', 'status_received_at', 'last_check_in_attempt_at', 'last_successful_check_in_at', 'last_check_in_error', 'max_seen_time', 'mode', 'read_only_reason'])]
#[Hidden(['token'])]
#[UseFactory(LicenseStateFactory::class)]
class LicenseState extends Model
{
    /** @use HasFactory<LicenseStateFactory> */
    use HasFactory;

    /**
     * The table has no `created_at` column.
     *
     * @var string|null
     */
    public const CREATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_expires_at' => 'datetime',
            'token_expires_at' => 'datetime',
            'port_status' => PortStatus::class,
            'license_status' => LicenseStatus::class,
            'status_received_at' => 'datetime',
            'last_check_in_attempt_at' => 'datetime',
            'last_successful_check_in_at' => 'datetime',
            'max_seen_time' => 'datetime',
            'mode' => LicenseMode::class,
            'read_only_reason' => ReadOnlyReason::class,
            'updated_at' => 'datetime',
        ];
    }
}
