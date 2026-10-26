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
            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->enum('opened_by_role', ['customer', 'owner']);
            $table->string('category', 80);
            $table->text('description');
            $table->dateTime('owner_response_deadline_at');
            $table->dateTime('owner_responded_at')->nullable();
            $table->enum('status', [
                'open',
                'under_review',
                'resolved_to_customer',
                'resolved_to_owner',
                'resolved_split',
            ])->default('open');
            $table->text('resolution_notes')->nullable();
            $table->unsignedBigInteger('refund_amount_awarded')->default(0);
            $table->unsignedBigInteger('payout_amount_awarded')->default(0);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'owner_response_deadline_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
