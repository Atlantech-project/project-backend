<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * DEC-28 open item 1 / AI data gap G-09: the optional number of containers the vessel loads at this port.
 * When empty, the AI sets loading = discharge and flags `loading_estimated`. Allowed only for container vessels
 * (the Backend validates it). Additive only (CT-17 minor version, ai-impact).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('berthing_requests', function (Blueprint $table) {
            $table->integer('loading_container_count')->nullable()->after('deadweight_tonnes')->comment('Containers to load at this port; NULL = not entered (DEC-28: the AI then estimates loading = discharge)');
        });

        DB::statement('ALTER TABLE `berthing_requests` ADD CONSTRAINT `chk_br_loading` CHECK ((`loading_container_count` is null) or (`loading_container_count` >= 0))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `berthing_requests` DROP CHECK `chk_br_loading`');

        Schema::table('berthing_requests', function (Blueprint $table) {
            $table->dropColumn('loading_container_count');
        });
    }
};
