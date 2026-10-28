<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Enums\PilotRank;
use Database\Factories\MasterData\PilotProfileFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1:1 with user. App rule: user must hold MARINE_PILOT in MARINE_SERVICES (D-29).
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'pilot_profiles', key: 'user_id', incrementing: false, keyType: 'int')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['user_id', 'pilot_rank'])]
#[UseFactory(PilotProfileFactory::class)]
class PilotProfile extends Model
{
    /** @use HasFactory<PilotProfileFactory> */
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
            'pilot_rank' => PilotRank::class,
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<PilotSchedule, $this>
     */
    public function pilotSchedulesByPilotUser(): HasMany
    {
        return $this->hasMany(PilotSchedule::class, 'pilot_user_id');
    }
}
