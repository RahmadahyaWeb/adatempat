<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues');
            $table->foreignId('space_id')->constrained('spaces');
            $table->foreignId('user_id')->constrained('users');
            $table->string('code')->unique();
            $table->date('booking_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email');
            $table->text('notes')->nullable();
            $table->decimal('subtotal_amount', 12, 2);
            $table->decimal('addons_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('pending_payment');
            $table->string('payment_method')->default('qris');
            $table->string('qr_checkin_token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users');
            $table->json('cancellation_policy_snapshot')->nullable();
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['space_id', 'booking_date', 'status']);
            $table->index(['user_id', 'booking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
