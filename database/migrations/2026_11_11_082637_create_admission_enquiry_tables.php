<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admission enquiries: a family asks for a seat in a class at a branch for an academic year. The front desk follows
 * up on the enquiry, registers it, and takes it through the admission steps (age approval, fee defaulter check,
 * documents, entry test) until the child is admitted. Each follow-up and step is kept as an activity.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('enquiry_number')->unique();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->unsignedInteger('year_id');
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('student_firstname');
            $table->string('student_lastname');
            $table->unsignedTinyInteger('gender_id')->default(0);
            $table->date('birthdate')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('guardian_name');
            $table->string('guardian_relation')->nullable();
            $table->string('guardian_phone');
            $table->string('guardian_email')->nullable();
            $table->string('address')->nullable();
            $table->string('source');
            $table->string('status');
            $table->string('stage')->nullable();
            $table->foreignId('sibling_admission_id')->nullable()->constrained('admissions')->nullOnDelete();
            $table->json('documents_received')->nullable();
            $table->dateTime('test_at')->nullable();
            $table->boolean('test_passed')->nullable();
            $table->string('test_remarks')->nullable();
            $table->date('next_follow_up_on')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'year_id', 'school_class_id']);
            $table->index(['status', 'stage']);
        });

        Schema::create('enquiry_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_enquiry_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('enquiry_activities');
        Schema::dropIfExists('admission_enquiries');
    }
};
