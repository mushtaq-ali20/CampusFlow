<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The periods of the school day are configurable. A timetable entry puts a subject and its teacher
 * in a period of a weekday for a section.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('timetable_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_break')->default(false);
            $table->timestamps();
        });

        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timetable_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('teacher_id');
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'timetable_period_id', 'day_of_week'], 'timetable_section_slot_unique');
            $table->index(['teacher_id', 'timetable_period_id', 'day_of_week'], 'timetable_teacher_slot_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('timetable_periods');
    }
};
