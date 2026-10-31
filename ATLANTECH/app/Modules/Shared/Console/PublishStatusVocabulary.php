<?php

namespace App\Modules\Shared\Console;

use BackedEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ReflectionEnum;

/**
 * Generates PortHub contracts/status-vocabulary.json (CT-21) from the PHP enums in app/Modules/<Module>/Enums.
 * Each enum names its columns in its PHPDoc as `table.column`; an enum tagged `@derived` goes under "derived".
 */
class PublishStatusVocabulary extends Command
{
    /**
     * @var string
     */
    protected $signature = 'atlantech:contracts-status-vocabulary
        {--output= : Where to write status-vocabulary.json (default: the PortHub contracts folder above this app)}';

    /**
     * @var string
     */
    protected $description = 'Generate contracts/status-vocabulary.json from the PHP enums (CT-21)';

    public function handle(): int
    {
        $output = (string) ($this->option('output') ?: base_path('../../contracts/status-vocabulary.json'));

        if (! is_dir(dirname($output))) {
            $this->error('Folder not found: '.dirname($output).'. Run from the PortHub checkout or pass --output.');

            return self::FAILURE;
        }

        $vocabulary = ['version' => '1.0', 'columns' => [], 'derived' => []];

        foreach (File::glob(app_path('Modules/*/Enums/*.php')) as $path) {
            $module = basename(dirname($path, 2));
            $enum = new ReflectionEnum(implode('\\', ['App', 'Modules', $module, 'Enums', basename($path, '.php')]));
            $document = (string) $enum->getDocComment();

            preg_match_all('/`([a-z0-9_]+\.[a-z0-9_]+)`/', $document, $matches);

            if ($matches[1] === []) {
                $this->error("{$enum->getName()} names no `table.column` in its PHPDoc.");

                return self::FAILURE;
            }

            $section = str_contains($document, '@derived') ? 'derived' : 'columns';
            $values = array_map(fn (BackedEnum $case): string => (string) $case->value, $enum->getName()::cases());

            foreach ($matches[1] as $column) {
                $vocabulary[$section][$column] = $values;
            }
        }

        ksort($vocabulary['columns']);
        ksort($vocabulary['derived']);

        if (file_put_contents($output, json_encode($vocabulary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n") === false) {
            $this->error("Could not write {$output}.");

            return self::FAILURE;
        }

        $this->info('Wrote '.(realpath($output) ?: $output).'.');

        return self::SUCCESS;
    }
}
