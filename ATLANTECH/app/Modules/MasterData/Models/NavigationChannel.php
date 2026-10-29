<?php

namespace App\Modules\MasterData\Models;

use Database\Factories\MasterData\NavigationChannelFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The `navigation_channels` table.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'navigation_channels')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['name', 'depth', 'depth_datum', 'width', 'speed_limit_knots', 'is_active'])]
#[UseFactory(NavigationChannelFactory::class)]
class NavigationChannel extends Model
{
    /** @use HasFactory<NavigationChannelFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'depth' => 'decimal:2',
            'width' => 'decimal:2',
            'speed_limit_knots' => 'decimal:1',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ChannelWaypoint, $this>
     */
    public function channelWaypointsByChannel(): HasMany
    {
        return $this->hasMany(ChannelWaypoint::class, 'channel_id');
    }
}
