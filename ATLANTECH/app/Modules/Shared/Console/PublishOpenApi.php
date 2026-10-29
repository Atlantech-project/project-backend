<?php

namespace App\Modules\Shared\Console;

use Illuminate\Console\Command;

/**
 * Copies openapi/port-api.yaml, the source owned by the Backend Team (DEC-13), to PortHub
 * contracts/openapi/port-api.yaml (CT-02). The copy is byte for byte; nobody edits the PortHub file by hand.
 */
class PublishOpenApi extends Command
{
    /**
     * @var string
     */
    protected $signature = 'atlantech:contracts-openapi
        {--output= : Where to write port-api.yaml (default: the PortHub contracts/openapi folder above this app)}';

    /**
     * @var string
     */
    protected $description = 'Publish openapi/port-api.yaml to PortHub contracts/openapi (CT-02)';

    public function handle(): int
    {
        $output = (string) ($this->option('output') ?: base_path('../../contracts/openapi/port-api.yaml'));
        $folder = dirname($output);

        if (! is_dir($folder) && ! is_dir(dirname($folder))) {
            $this->error("Folder not found: {$folder}. Run from the PortHub checkout or pass --output.");

            return self::FAILURE;
        }

        if (! is_dir($folder) && ! mkdir($folder)) {
            $this->error("Could not create {$folder}.");

            return self::FAILURE;
        }

        if (! copy(base_path('openapi/port-api.yaml'), $output)) {
            $this->error("Could not write {$output}.");

            return self::FAILURE;
        }

        $this->info('Wrote '.(realpath($output) ?: $output).'.');

        return self::SUCCESS;
    }
}
