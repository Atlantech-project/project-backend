<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('port_profiles', function (Blueprint $table) {
            $table->tinyInteger('id')->primary()->default(1);
            $table->char('public_id', 36)->unique()->comment('Same value as SaaS platform_port_registry.public_id (read-only)');
            $table->string('port_code', 10)->nullable()->comment('UN/LOCODE, from SaaS');
            $table->string('governorate_code', 6)->comment('ISO 3166-2:EG, same codes as SaaS governorate');
            $table->string('name', 150);
            $table->decimal('latitude', 9, 6)->comment('Reference point for weather/tide pulls');
            $table->decimal('longitude', 9, 6);
            $table->string('timezone', 64)->comment('IANA, e.g. Africa/Cairo');
            $table->json('boundary_coordinates')->nullable()->comment('GeoJSON Polygon, [lon, lat]');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent();

            $table->comment('Singleton per deployment. GM is a user_role_assignment, not a column here.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('port_profiles');
    }
};
