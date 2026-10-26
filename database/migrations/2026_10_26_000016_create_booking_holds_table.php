<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('expires_at');
            $table->string('idempotency_key', 100)->unique();
            $table->enum('status', ['active', 'reconciling', 'converted', 'expired', 'released'])->default('active');
            $table->timestamps();

            $table->index(['venue_id', 'starts_at', 'ends_at']);
            $table->index(['venue_id', 'status', 'expires_at']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_holds');
    }
};
