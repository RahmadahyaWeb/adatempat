<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('order_id', 50)->index('idx_payments_order_id');
            $table->string('midtrans_transaction_id', 100)->nullable()->index('idx_payments_midtrans_id');
            $table->unsignedBigInteger('gross_amount'); // Integer Rupiah
            $table->string('payment_type', 50)->default('qris');
            $table->string('status', 50)->default('pending');
            $table->text('qr_string')->nullable();
            $table->text('qr_url')->nullable();
            $table->timestamp('expiry_time')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status'], 'idx_payments_booking_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
