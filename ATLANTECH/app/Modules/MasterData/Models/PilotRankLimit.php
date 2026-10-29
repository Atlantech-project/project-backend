<?php

namespace App\Modules\MasterData\Models;

use App\Modules\MasterData\Enums\PilotRank;
use Database\Factories\MasterData\PilotRankLimitFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Settings screen: one row per rank.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'pilot_rank_limits', key: 'pilot_rank', incrementing: false, keyType: 'string')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['pilot_rank', 'max_vessel_loa'])]
#[UseFactory(PilotRankLimitFactory::class)]
class PilotRankLimit extends Model
{
    /** @use HasFactory<PilotRankLimitFactory> */
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
            'max_vessel_loa' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }
}
