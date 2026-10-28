<?php

namespace App\Modules\MasterData\Models;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Enums\PortSettingValueType;
use Database\Factories\MasterData\PortSettingFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The `port_settings` table.
 * `updated_at` is the version token for the 409 check.
 */
#[Table(name: 'port_settings')]
#[DateFormat('Y-m-d H:i:s.u')]
#[Fillable(['setting_key', 'setting_value', 'value_type', 'description', 'updated_by'])]
#[UseFactory(PortSettingFactory::class)]
class PortSetting extends Model
{
    /** @use HasFactory<PortSettingFactory> */
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
            'value_type' => PortSettingValueType::class,
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
