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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 50)->unique('uq_bookings_code');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('venue_id')->constrained('venues')->onDelete('restrict');
            $table->unsignedBigInteger('total_amount'); // Integer Rupiah
            $table->string('payment_status', 50)->default('pending');
            $table->string('check_in_status', 50)->default('unclaimed');
            $table->string('qr_token', 64)->unique('uq_bookings_qr');
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->text('qris_url')->nullable();
            $table->timestamp('qris_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rescheduled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'payment_status', 'created_at'], 'idx_bookings_player');
            $table->index(['venue_id', 'created_at'], 'idx_bookings_venue_timeline');
            $table->index(['payment_status', 'check_in_status'], 'idx_bookings_status');
        });

        Schema::create('booking_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('court_slot_id')->constrained('court_slots')->onDelete('restrict');
            $table->unsignedBigInteger('price_locked'); // Integer Rupiah
            $table->timestamps();

            $table->unique(['booking_id', 'court_slot_id'], 'uq_booking_slot');
            $table->index('booking_id', 'idx_booking_items_booking');
            $table->index('court_slot_id', 'idx_booking_items_court_slot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_items');
        Schema::dropIfExists('bookings');
    }
};
