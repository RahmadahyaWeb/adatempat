<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_code', 32)->unique();
            $table->foreignId('booking_id')->unique()->constrained('bookings')->restrictOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('against_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('reason_type', [
                'service_not_provided',
                'venue_condition',
                'booking_mismatch',
                'payment_or_refund',
                'other',
            ]);
            $table->text('description');
            $table->enum('status', ['awaiting_response', 'under_review', 'resolved', 'rejected'])->default('awaiting_response');
            $table->dateTime('response_deadline');
            $table->enum('resolution_type', ['full_owner_payout', 'full_customer_refund', 'partial_split'])->nullable();
            $table->unsignedBigInteger('customer_refund_amount')->default(0);
            $table->unsignedBigInteger('owner_payout_amount')->default(0);
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'response_deadline']);
            $table->index(['opened_by', 'status']);
            $table->index(['against_user_id', 'status']);
        });

        Schema::create('dispute_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->restrictOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->dateTime('created_at');

            $table->index(['dispute_id', 'created_at']);
        });

        Schema::create('dispute_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path', 255);
            $table->string('original_name', 180);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('caption', 255)->nullable();
            $table->dateTime('created_at');

            $table->index(['dispute_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('dispute_messages');
        Schema::dropIfExists('disputes');
    }
};
