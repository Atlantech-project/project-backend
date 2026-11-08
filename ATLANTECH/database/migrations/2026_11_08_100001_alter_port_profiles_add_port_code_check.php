<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * DEC-28 / AI data gap G-10: `port_code` is compared by the AI with `container_manifest_entries.port_of_discharge`, so it
 * takes the same UN/LOCODE shape as the other port columns (five characters, upper case). The column stays varchar(10)
 * because it mirrors the SaaS registry. NULL stays allowed: the AI fails the job with CONFIGURATION_ERROR (DEC-28).
 * Additive only (CT-17 minor version, ai-impact).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `port_profiles` ADD CONSTRAINT `chk_port_profile_code` CHECK (((`port_code` is null) or regexp_like(`port_code`,'^[A-Z]{2}[A-Z2-9]{3}$','c')))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `port_profiles` DROP CHECK `chk_port_profile_code`');
    }
};
