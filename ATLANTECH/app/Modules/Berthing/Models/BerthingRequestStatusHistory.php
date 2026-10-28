<?php

namespace App\Modules\Berthing\Models;

use App\Modules\Access\Models\User;
use App\Modules\Berthing\Enums\BerthingRequestStatus;
use Database\Factories\Berthing\BerthingRequestStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CUSTOMS_APPROVED row = who/when Customs Approval was recorded (D1, D4).
 */
#[Table(name: 'berthing_request_status_histories', timestamps: false)]
#[Fillable(['berthing_request_id', 'from_status', 'to_status', 'changed_by', 'changed_at', 'note'])]
#[UseFactory(BerthingRequestStatusHistoryFactory::class)]
class BerthingRequestStatusHistory extends Model
{
    /** @use HasFactory<BerthingRequestStatusHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => BerthingRequestStatus::class,
            'to_status' => BerthingRequestStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BerthingRequest, $this>
     */
    public function berthingRequest(): BelongsTo
    {
        return $this->belongsTo(BerthingRequest::class, 'berthing_request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
