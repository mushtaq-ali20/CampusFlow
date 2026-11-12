<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guidance and violation: the categories of good conduct and misconduct with their points, and the records of
 * the students. A record keeps the points of its category when it was recorded, positive for a merit and negative
 * for a violation, so that changing a category later does not change the past records.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('discipline_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind');
            $table->unsignedInteger('points')->default(0);
            $table->string('consequence')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('discipline_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discipline_category_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->integer('points');
            $table->string('consequence')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'date']);
            $table->index(['section_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discipline_records');
        Schema::dropIfExists('discipline_categories');
    }
};
