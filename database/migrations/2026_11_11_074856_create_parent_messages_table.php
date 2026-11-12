<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WhatsApp messages prepared for the parents of a student. The school sends them from its own WhatsApp:
 * CampusFlow opens the chat with the message written, and keeps a copy of it here, with what it was about.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parent_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->unsignedInteger('campus_id')->nullable();
            $table->foreign('campus_id')->references('id')->on('campuses')->nullOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('recipient_name')->nullable();
            $table->string('phone');
            $table->text('body');
            $table->nullableMorphs('about');
            $table->unsignedInteger('sent_by')->nullable();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_messages');
    }
};
