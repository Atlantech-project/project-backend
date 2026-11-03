<?php

namespace Database\Seeders;

use App\Modules\MasterData\Models\PortSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Backend `port_settings` keys of `reference-seed.json` with the CT-18 section 3 rules: missing keys are
 * inserted; the value follows a new default only while `updated_by` is NULL; the description, min and max are always updated.
 */
class PortSettingsSeeder extends Seeder
{
    public function run(?string $specPath = null): void
    {
        /** @var array{port_settings: list<array<string, mixed>>} $spec */
        $spec = json_decode((string) file_get_contents($specPath ?? database_path('seeders/data/reference-seed.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(fn () => $this->upsert($spec['port_settings']));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsert(array $rows): void
    {
        foreach ($rows as $row) {
            $setting = PortSetting::query()->firstOrNew(['setting_key' => $row['setting_key']]);

            $setting->description = $row['description'] ?? null;
            $setting->min_value = $row['min'] ?? null;
            $setting->max_value = $row['max'] ?? null;

            if (! $setting->exists || $setting->updated_by === null) {
                $setting->setting_value = (string) $row['value'];
                $setting->value_type = $row['value_type'];
            }

            if ($setting->isDirty()) {
                $setting->save();
            }
        }
    }
}
