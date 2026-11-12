<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment, performance evaluation and account payable:
 * - job_openings and job_applicants: the vacancies of a branch and the people who applied, entered by the front desk;
 * - evaluation_criteria, appraisal_cycles, appraisals and appraisal_scores: the yearly or termly evaluation of the
 *   employees against weighted criteria;
 * - vendors, expense_categories, vendor_bills and vendor_payments: what the school owes its suppliers and pays them.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_openings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->string('title');
            $table->string('department')->nullable();
            $table->string('employment_type');
            $table->unsignedSmallInteger('positions')->default(1);
            $table->text('description')->nullable();
            $table->string('status');
            $table->date('closes_on')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'status']);
        });

        Schema::create('job_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_opening_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->string('firstname');
            $table->string('lastname');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('cnic')->nullable();
            $table->string('qualification')->nullable();
            $table->unsignedTinyInteger('experience_years')->default(0);
            $table->unsignedInteger('expected_salary')->nullable();
            $table->string('stage');
            $table->dateTime('interview_at')->nullable();
            $table->unsignedInteger('interviewer_id')->nullable();
            $table->foreign('interviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('interview_notes')->nullable();
            $table->unsignedInteger('offered_salary')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('employee_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'stage']);
        });

        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('weight')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('appraisal_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        Schema::create('appraisals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->unsignedInteger('evaluator_id')->nullable();
            $table->foreign('evaluator_id')->references('id')->on('users')->nullOnDelete();
            $table->string('status');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->text('employee_comment')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['appraisal_cycle_id', 'employee_profile_id']);
            $table->index(['campus_id', 'status']);
        });

        Schema::create('appraisal_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('weight');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['appraisal_id', 'evaluation_criterion_id']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('ntn')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('bill_number')->nullable();
            $table->date('bill_date');
            $table->date('due_date');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('paid_amount')->default(0);
            $table->string('status');
            $table->string('description')->nullable();
            $table->unsignedInteger('recorded_by')->nullable();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->string('voucher_number')->unique();
            $table->unsignedInteger('amount');
            $table->date('paid_on');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->unsignedInteger('paid_by')->nullable();
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('appraisal_scores');
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('appraisal_cycles');
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('job_applicants');
        Schema::dropIfExists('job_openings');
    }
};
