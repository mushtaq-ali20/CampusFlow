<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school can run a morning and an afternoon shift. Each section studies in one shift, and each shift has
 * its own periods of the school day. A class can have sections with the same names in both shifts, such as
 * Class 1 - A in the morning and Class 1 - A in the afternoon.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('shift')->default('morning')->after('name');
            $table->unique(['campus_id', 'school_class_id', 'year_id', 'shift', 'name'], 'sections_shift_name_unique');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique(['campus_id', 'school_class_id', 'year_id', 'name']);
        });

        Schema::table('timetable_periods', function (Blueprint $table) {
            $table->string('shift')->default('morning')->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_periods', function (Blueprint $table) {
            $table->dropColumn('shift');
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->unique(['campus_id', 'school_class_id', 'year_id', 'name']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropUnique('sections_shift_name_unique');
            $table->dropColumn('shift');
        });
    }
};
