<?php

namespace App\Modules\Access\Models;

use App\Modules\Access\Enums\DevicePlatform;
use Database\Factories\Access\UserDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mobile device a user signed in on. Never deleted: `revoked_at` is set instead.
 */
#[Table(name: 'user_devices', timestamps: false)]
#[Fillable(['user_id', 'device_id', 'platform', 'app_version', 'last_seen_at', 'revoked_at', 'push_token', 'push_token_updated_at'])]
#[Hidden(['push_token'])]
#[UseFactory(UserDeviceFactory::class)]
class UserDevice extends Model
{
    /** @use HasFactory<UserDeviceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'push_token_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
