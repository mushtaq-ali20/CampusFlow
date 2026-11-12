<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee collection: fee heads and the fee structure of each class, discounts, the monthly vouchers of the
 * admitted students, their payments and the receipts. Amounts are whole rupees.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('frequency');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year_id');
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnDelete();
            $table->unsignedInteger('campus_id')->nullable();
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('mode');
            $table->unsignedInteger('value');
            $table->foreignId('fee_head_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admission_fee_discount', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_discount_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admission_id', 'fee_discount_id']);
        });

        Schema::create('fee_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year_id');
            $table->foreign('year_id')->references('id')->on('years')->cascadeOnDelete();
            $table->date('month');
            $table->date('due_date');
            $table->integer('gross_amount')->default(0);
            $table->integer('discount_amount')->default(0);
            $table->integer('fine_amount')->default(0);
            $table->integer('net_amount')->default(0);
            $table->integer('paid_amount')->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamps();

            $table->unique(['admission_id', 'month']);
            $table->index(['campus_id', 'school_class_id', 'section_id', 'month']);
        });

        Schema::create('fee_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('description');
            $table->unsignedInteger('amount');
            $table->timestamps();
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->nullable()->unique();
            $table->foreignId('fee_voucher_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->date('paid_on');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->unsignedInteger('collected_by')->nullable();
            $table->foreign('collected_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'paid_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_voucher_items');
        Schema::dropIfExists('fee_vouchers');
        Schema::dropIfExists('admission_fee_discount');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_heads');
    }
};
