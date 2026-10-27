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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('username', 50)->unique()->comment('Login name, stored lowercase');
            $table->string('password_hash', 255);
            $table->string('phone', 30)->nullable()->comment('Contact only, not used for login');
            $table->boolean('is_active')->default(true)->comment('Active users count against license max_users');
            $table->boolean('must_change_password')->default(false)->comment('1 = one-time password in use; must change before any other action');
            $table->dateTime('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
