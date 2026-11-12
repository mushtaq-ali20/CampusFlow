<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admission of a student to a section of a class at a branch for an academic year.
 * A student has one admission per academic year; promotion creates the admission of the next year.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number')->unique();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year_id');
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnDelete();
            $table->date('admitted_on');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'year_id']);
            $table->index(['campus_id', 'school_class_id', 'section_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
