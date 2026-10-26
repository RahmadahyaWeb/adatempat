<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('scheduled_plan_id')->nullable()->constrained('subscription_plans')->restrictOnDelete();
            $table->foreignId('retained_business_id')->nullable()->constrained('businesses')->restrictOnDelete();
            $table->boolean('is_trial')->default(false);
            $table->enum('status', [
                'trialing',
                'pending_payment',
                'active',
                'grace_period',
                'suspended',
                'pending_reverification',
                'cancelled',
            ])->default('pending_payment');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('grace_ends_at');
            $table->dateTime('suspended_at')->nullable();
            $table->foreignId('reverified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['owner_id', 'status', 'starts_at', 'ends_at']);
            $table->index(['status', 'grace_ends_at']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->enum('invoice_type', ['booking', 'subscription', 'subscription_upgrade', 'ad_booking']);
            $table->unsignedBigInteger('reference_id');
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('issuer_name');
            $table->unsignedBigInteger('subtotal_amount');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('total_amount');
            $table->enum('status', ['unpaid', 'paid', 'void', 'refunded'])->default('unpaid');
            $table->json('line_items_json');
            $table->dateTime('issued_at');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_type', 'reference_id']);
            $table->index(['recipient_user_id', 'status', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
    }
};
