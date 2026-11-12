<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parent teacher meetings: a meeting day at a branch for some sections, with a time slot for the parents of each
 * student with their class teacher, the notes of the meeting and the actions agreed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ptm_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->unsignedInteger('year_id');
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->unsignedSmallInteger('slot_minutes')->default(10);
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ptm_event_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptm_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unique(['ptm_event_id', 'section_id']);
        });

        Schema::create('ptm_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ptm_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('teacher_id')->nullable();
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
            $table->time('slot_time');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->text('action_items')->nullable();
            $table->date('action_due_on')->nullable();
            $table->dateTime('action_done_at')->nullable();
            $table->timestamps();

            $table->unique(['ptm_event_id', 'admission_id']);
            $table->index(['ptm_event_id', 'section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ptm_meetings');
        Schema::dropIfExists('ptm_event_section');
        Schema::dropIfExists('ptm_events');
    }
};
