<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employees, leave requests and payroll:
 * - employee_profiles: the HR file of a user (staff or teacher) with the basic salary;
 * - employee_pay_items: the fixed allowances and the fixed deductions set up for an employee;
 * - leave_allowances: the days a year of a leave type, and whether it is paid;
 * - leave_requests: a request for days of leave, approved or rejected;
 * - payroll_runs, salary_slips and salary_slip_items: the salary of a branch for a month.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->string('employee_code')->unique();
            $table->string('designation');
            $table->string('department')->nullable();
            $table->string('employment_type');
            $table->string('status');
            $table->date('joined_on');
            $table->date('left_on')->nullable();
            $table->string('cnic')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->unsignedInteger('basic_salary')->default(0);
            $table->timestamps();

            $table->index(['campus_id', 'status']);
        });

        Schema::create('employee_pay_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        Schema::create('leave_allowances', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('leave_type_id')->unique();
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('days_per_year')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->unsignedInteger('leave_type_id');
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->unsignedSmallInteger('days');
            $table->string('reason')->nullable();
            $table->string('status');
            $table->unsignedInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'status']);
            $table->index(['user_id', 'from_date']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->string('month', 7);
            $table->string('status');
            $table->unsignedInteger('generated_by')->nullable();
            $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['campus_id', 'month']);
        });

        Schema::create('salary_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->string('slip_number')->unique();
            $table->unsignedInteger('basic_salary');
            $table->unsignedInteger('allowances_total')->default(0);
            $table->unsignedInteger('deductions_total')->default(0);
            $table->unsignedSmallInteger('unpaid_days')->default(0);
            $table->unsignedInteger('unpaid_deduction')->default(0);
            $table->unsignedInteger('net_salary')->default(0);
            $table->date('paid_on')->nullable();
            $table->string('method')->nullable();
            $table->unsignedInteger('paid_by')->nullable();
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_profile_id']);
        });

        Schema::create('salary_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_slip_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->unsignedInteger('amount');
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_slip_items');
        Schema::dropIfExists('salary_slips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_allowances');
        Schema::dropIfExists('employee_pay_items');
        Schema::dropIfExists('employee_profiles');
    }
};
