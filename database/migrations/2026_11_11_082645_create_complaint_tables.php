<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complaints from parents, students and staff. A category sets the time limit, who handles a new complaint and to
 * whom it is escalated when the time limit passes. Every assignment, comment, escalation and resolution is kept as
 * an update of the complaint.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('response_hours')->default(24);
            $table->unsignedInteger('assign_to_id')->nullable();
            $table->foreign('assign_to_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('escalate_to_id')->nullable();
            $table->foreign('escalate_to_id')->references('id')->on('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number')->unique();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('complaint_category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('students')->nullOnDelete();
            $table->string('complainant_type');
            $table->string('complainant_name');
            $table->string('complainant_phone')->nullable();
            $table->string('subject');
            $table->text('description');
            $table->string('priority');
            $table->string('status');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('due_at');
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'status']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('complaint_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->text('note');
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_updates');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_categories');
    }
};
