<?php

namespace App\Modules\Baplie\Models;

use App\Modules\Baplie\Enums\BaplieParseIssueSeverity;
use Database\Factories\Baplie\BaplieParseIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-1.2.4 diagnostics.
 * Rows are inserted by an AI worker; the Backend never changes them (CT-15).
 */
#[Table(name: 'baplie_parse_issues', timestamps: false)]
#[Fillable([])]
#[UseFactory(BaplieParseIssueFactory::class)]
class BaplieParseIssue extends Model
{
    /** @use HasFactory<BaplieParseIssueFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => BaplieParseIssueSeverity::class,
        ];
    }

    /**
     * @return BelongsTo<BaplieFile, $this>
     */
    public function baplieFile(): BelongsTo
    {
        return $this->belongsTo(BaplieFile::class, 'baplie_file_id');
    }
}
