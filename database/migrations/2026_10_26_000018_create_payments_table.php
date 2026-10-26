<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->enum('payable_type', ['booking', 'subscription', 'ad_booking']);
            $table->unsignedBigInteger('payable_id');
            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->string('gateway_provider', 50);
            $table->string('gateway_transaction_id', 120)->nullable()->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->text('qris_payload')->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('gateway_fee_amount')->default(0);
            $table->enum('status', [
                'pending',
                'verifying',
                'paid',
                'failed',
                'expired',
                'reconciliation_required',
                'refunded',
                'partially_refunded',
            ])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['payer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
