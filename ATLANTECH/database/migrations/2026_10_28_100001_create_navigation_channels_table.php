<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('navigation_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique('name');
            $table->decimal('depth', 6, 2)->comment('Controlling (minimum) charted depth, metres — input to UKC');
            $table->string('depth_datum', 40)->comment('e.g. LAT; must equal the active tide_constituent_set.datum_reference');
            $table->decimal('width', 7, 2)->comment('metres — input to squat');
            $table->decimal('speed_limit_knots', 4, 1)->comment('Max permitted transit speed, entered by admin; worst-case input to squat');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE `navigation_channels` ADD CONSTRAINT `chk_channel_dimensions` CHECK (((depth > 0) and (width > 0) and (speed_limit_knots > 0)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_channels');
    }
};
