<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();
            $table->string('banner_image_path', 255);
            $table->string('headline', 120);
            $table->string('cta_url', 255);
            $table->unsignedBigInteger('price_amount');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->enum('status', [
                'pending_payment',
                'confirmed',
                'active',
                'completed',
                'cancelled',
                'expired',
            ])->default('pending_payment');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at', 'status']);
            $table->index(['business_id', 'status']);
            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_bookings');
    }
};
