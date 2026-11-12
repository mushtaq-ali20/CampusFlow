<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The grading scale: a percentage from the minimum of a band gets the grade of the band, such as A+ from 90%.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->string('grade')->unique();
            $table->decimal('min_percentage', 5, 2)->unique();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_bands');
    }
};
