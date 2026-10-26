<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('ad_booking_id')->nullable()->constrained('ad_bookings')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->enum('reason_type', [
                'customer_cancellation',
                'owner_cancellation',
                'late_payment_unfulfillable',
                'dispute_resolution',
                'ad_cancellation',
            ]);
            $table->text('reason_notes');
            $table->enum('status', [
                'requested',
                'under_review',
                'approved',
                'rejected',
                'processing',
                'succeeded',
                'failed',
            ])->default('requested');
            $table->string('idempotency_key', 100)->unique();
            $table->string('gateway_refund_id', 120)->nullable();
            $table->string('manual_transfer_reference', 120)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('succeeded_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['payment_id', 'status']);
            $table->index(['recipient_user_id', 'status']);
        });

        Schema::create('payout_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('bank_code', 30);
            $table->string('bank_name', 100);
            $table->text('account_number_encrypted');
            $table->string('account_number_last4', 4);
            $table->string('account_holder_name', 140);
            $table->enum('verification_status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'verification_status']);
        });

        Schema::create('payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('payout_account_id')->nullable()->constrained('payout_accounts')->nullOnDelete();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('gateway_fee_deducted')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->enum('status', [
                'held',
                'eligible',
                'processing',
                'paid',
                'failed',
                'on_hold',
                'cancelled',
            ])->default('held');
            $table->string('transfer_reference', 120)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('eligible_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['status', 'eligible_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->char('transaction_group_ulid', 26);
            $table->enum('account_code', [
                'customer_cash',
                'gateway_clearing',
                'escrow_liability',
                'owner_payable',
                'platform_subsidy_expense',
                'gateway_fee_expense',
                'subscription_revenue',
                'ad_revenue',
                'refund_payable',
            ]);
            $table->enum('entry_type', ['debit', 'credit']);
            $table->unsignedBigInteger('amount');
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->text('description');
            $table->dateTime('created_at');

            $table->index(['transaction_group_ulid', 'account_code']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payout_accounts');
        Schema::dropIfExists('refunds');
    }
};
