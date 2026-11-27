<?php

namespace App\Modules\Yard\Services;

use App\Modules\Shared\Enums\RunStatus;
use App\Modules\Yard\Models\YardRun;

/**
 * Removing slots, blocks or zones is refused while a yard run of the terminal is QUEUED or RUNNING: the worker may have
 * read a slot that it has not stored yet (P2-F4 R14, lead 2026-11-27).
 */
class YardRunGuard
{
    public function isRunInProgress(int $terminalId): bool
    {
        return YardRun::query()
            ->whereIn('status', [RunStatus::Queued, RunStatus::Running])
            ->whereHas('terminalRecommendation', fn ($recommendation) => $recommendation->where('terminal_id', $terminalId))
            ->exists();
    }
}
