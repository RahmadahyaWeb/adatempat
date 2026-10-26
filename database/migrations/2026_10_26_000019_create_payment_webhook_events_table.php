<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_provider', 50);
            $table->string('gateway_event_id', 140);
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload_json');
            $table->enum('processing_status', ['received', 'processed', 'failed'])->default('received');
            $table->unsignedInteger('duplicate_delivery_count')->default(0);
            $table->dateTime('last_duplicate_received_at')->nullable();
            $table->text('processing_notes')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway_provider', 'gateway_event_id']);
            $table->index(['payment_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
