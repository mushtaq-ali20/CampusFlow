<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class assessments of a subject in a section: class tests, quizzes, assignments, projects and observations, with
 * the marks of each student. Each assessment counts towards one exam term (quarterly, half yearly or annual).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('exam');
            $table->string('type');
            $table->string('title');
            $table->unsignedInteger('total_marks');
            $table->date('held_on');
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['section_id', 'exam']);
        });

        Schema::create('assessment_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->decimal('marks', 6, 2);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'admission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_marks');
        Schema::dropIfExists('assessments');
    }
};
