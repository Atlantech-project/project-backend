<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\User;
use Database\Factories\MasterData\TugMasterProfileFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 1:1 with user. App rule: user must hold TUG_MASTER in MARINE_SERVICES (D-29).
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'tug_master_profiles', key: 'user_id', incrementing: false, keyType: 'int')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['user_id', 'assigned_tugboat_id'])]
#[UseFactory(TugMasterProfileFactory::class)]
class TugMasterProfile extends Model
{
    /** @use HasFactory<TugMasterProfileFactory> */
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
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tugboat, $this>
     */
    public function assignedTugboat(): BelongsTo
    {
        return $this->belongsTo(Tugboat::class, 'assigned_tugboat_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<TugMasterSchedule, $this>
     */
    public function tugMasterSchedulesByTugMasterUser(): HasMany
    {
        return $this->hasMany(TugMasterSchedule::class, 'tug_master_user_id');
    }
}
