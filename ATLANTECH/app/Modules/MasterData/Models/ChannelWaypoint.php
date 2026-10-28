<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\ChannelWaypointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `channel_waypoints` table.
 */
#[Table(name: 'channel_waypoints', timestamps: false)]
#[Fillable(['channel_id', 'sequence_order', 'label', 'latitude', 'longitude'])]
#[UseFactory(ChannelWaypointFactory::class)]
class ChannelWaypoint extends Model
{
    /** @use HasFactory<ChannelWaypointFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<NavigationChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(NavigationChannel::class, 'channel_id');
    }
}
