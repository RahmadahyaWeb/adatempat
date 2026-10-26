<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->enum('pricing_unit', ['hourly', 'session', 'daily']);
            $table->unsignedInteger('duration_units');
            $table->dateTime('booking_start');
            $table->dateTime('booking_end');
            $table->boolean('payment_required');
            $table->enum('status', [
                'pending_payment',
                'payment_verifying',
                'confirmed',
                'in_progress',
                'awaiting_completion',
                'completed',
                'cancelled_by_customer',
                'cancelled_by_owner',
                'cancelled_by_admin',
                'expired',
                'reconciliation_required',
                'disputed',
            ])->default('pending_payment');
            $table->foreignId('owner_promotion_id')->nullable()->constrained('venue_promotions')->restrictOnDelete();
            $table->foreignId('platform_promotion_id')->nullable()->constrained('venue_promotions')->restrictOnDelete();
            $table->unsignedBigInteger('snapshot_base_amount');
            $table->unsignedBigInteger('snapshot_owner_discount_amount')->default(0);
            $table->unsignedBigInteger('snapshot_platform_subsidy_amount')->default(0);
            $table->unsignedBigInteger('snapshot_actual_paid_amount');
            $table->unsignedBigInteger('snapshot_gateway_fee_amount')->default(0);
            $table->unsignedBigInteger('snapshot_net_owner_entitlement')->default(0);
            $table->json('snapshot_refund_policy_json');
            $table->foreignId('terms_acceptance_id')->constrained('terms_acceptances')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('completion_window_ends_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('slot_released_at')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'booking_start', 'booking_end']);
            $table->index(['business_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
