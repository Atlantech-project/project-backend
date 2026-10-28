<?php

namespace Database\Factories\Baplie;

use App\Modules\Baplie\Enums\BaplieParseIssueSeverity;
use App\Modules\Baplie\Models\BaplieFile;
use App\Modules\Baplie\Models\BaplieParseIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaplieParseIssue>
 */
class BaplieParseIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'baplie_file_id' => BaplieFile::factory(),
            'severity' => BaplieParseIssueSeverity::Error,
            'segment_ref' => null,
            'message' => fake()->lexify(str_repeat('?', 10)),
        ];
    }
}
