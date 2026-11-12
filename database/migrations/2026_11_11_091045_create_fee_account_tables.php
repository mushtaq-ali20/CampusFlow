<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee accounts beyond the monthly vouchers:
 * - installments: the balance of a voucher split into parts with their own due dates;
 * - adjustments: an increase or a reduction of a voucher, requested and then approved, which becomes a fee or a
 *   discount line of the voucher once approved;
 * - refunds: money given back from a payment, requested and then approved.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_voucher_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->unsignedInteger('amount');
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['fee_voucher_id', 'sequence']);
        });

        Schema::create('fee_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_voucher_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason');
            $table->string('status');
            $table->unsignedInteger('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('fee_voucher_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['campus_id', 'status']);
        });

        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_payment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('campus_id');
            $table->foreign('campus_id')->references('id')->on('campuses')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('reason');
            $table->string('method');
            $table->string('status');
            $table->date('refunded_on')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedInteger('decided_by')->nullable();
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->index(['campus_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_refunds');
        Schema::dropIfExists('fee_adjustments');
        Schema::dropIfExists('fee_installments');
    }
};
