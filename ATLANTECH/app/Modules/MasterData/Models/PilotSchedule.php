<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\User;
use Database\Factories\MasterData\PilotScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which pilot works which fixed shift on which day.
 */
#[Table(name: 'pilot_schedules', timestamps: false)]
#[Fillable(['pilot_user_id', 'shift_date', 'port_shift_id', 'created_by'])]
#[UseFactory(PilotScheduleFactory::class)]
class PilotSchedule extends Model
{
    /** @use HasFactory<PilotScheduleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<PilotProfile, $this>
     */
    public function pilotUser(): BelongsTo
    {
        return $this->belongsTo(PilotProfile::class, 'pilot_user_id');
    }

    /**
     * @return BelongsTo<PortShift, $this>
     */
    public function portShift(): BelongsTo
    {
        return $this->belongsTo(PortShift::class, 'port_shift_id');
    }
}
