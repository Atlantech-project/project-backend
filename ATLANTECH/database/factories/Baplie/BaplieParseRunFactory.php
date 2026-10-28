<?php

namespace Database\Factories\Baplie;

use App\Modules\Baplie\Enums\BaplieParseRunSource;
use App\Modules\Baplie\Models\BaplieFile;
use App\Modules\Baplie\Models\BaplieParseRun;
use App\Modules\Shared\Enums\RunStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaplieParseRun>
 */
class BaplieParseRunFactory extends Factory
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
            'source' => BaplieParseRunSource::File,
            'triggered_by' => null,
            'status' => RunStatus::Queued,
            'outcome' => null,
            'error_category' => null,
            'retry_count' => 0,
            'container_count' => null,
            'result_detail' => null,
            'worker_version' => null,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
            'result_handled_at' => null,
        ];
    }
}
