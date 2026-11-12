<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared documents for the staff, in folders. A document is shared with some roles (and teachers), at one branch
 * or all of them, and can be visible only from a date or until a date. The file itself is kept by the media library.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_folder_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('campus_id')->nullable();
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->date('visible_from')->nullable();
            $table->date('visible_until')->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('audience');
            $table->unique(['document_id', 'audience']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_audiences');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_folders');
    }
};
