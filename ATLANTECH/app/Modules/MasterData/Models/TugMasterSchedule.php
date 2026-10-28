<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\User;
use Database\Factories\MasterData\TugMasterScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which tug master works which fixed shift on which day.
 */
#[Table(name: 'tug_master_schedules', timestamps: false)]
#[Fillable(['tug_master_user_id', 'shift_date', 'port_shift_id', 'created_by'])]
#[UseFactory(TugMasterScheduleFactory::class)]
class TugMasterSchedule extends Model
{
    /** @use HasFactory<TugMasterScheduleFactory> */
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<TugMasterProfile, $this>
     */
    public function tugMasterUser(): BelongsTo
    {
        return $this->belongsTo(TugMasterProfile::class, 'tug_master_user_id');
    }

    /**
     * @return BelongsTo<PortShift, $this>
     */
    public function portShift(): BelongsTo
    {
        return $this->belongsTo(PortShift::class, 'port_shift_id');
    }
}
