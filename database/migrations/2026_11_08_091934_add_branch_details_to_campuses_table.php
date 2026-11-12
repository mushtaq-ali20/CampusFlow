<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campuses are the branches of the school.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campuses', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'address', 'phone']);
        });
    }
};
