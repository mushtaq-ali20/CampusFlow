<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A section (Class 1 - A) groups the students of a class at a branch for an academic year.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year_id');
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('class_teacher_id')->nullable();
            $table->foreign('class_teacher_id')->references('id')->on('teachers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['campus_id', 'school_class_id', 'year_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
