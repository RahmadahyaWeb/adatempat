<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->restrictOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->restrictOnDelete();
            $table->string('code', 50)->nullable()->index();
            $table->string('name', 140);
            $table->enum('funded_by', ['owner', 'platform'])->default('owner');
            $table->enum('discount_type', ['percentage', 'fixed_amount']);
            $table->unsignedBigInteger('discount_value');
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->boolean('is_combinable_with_owner_promo')->default(false);
            $table->unsignedInteger('max_quota')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['active', 'paused', 'suspended_by_admin', 'expired'])->default('active');
            $table->text('suspension_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['venue_id', 'status', 'starts_at', 'ends_at']);
            $table->index(['business_id', 'status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_promotions');
    }
};
