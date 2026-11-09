<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city');
            $table->text('address');
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->json('facilities')->nullable();
            $table->unsignedInteger('booking_horizon_days')->default(30);
            $table->boolean('allow_reschedule')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('subscription_status', 50)->default(SubscriptionStatus::Trial->value);
            $table->dateTime('subscription_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'subscription_status', 'city'], 'idx_venues_discovery');
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('name');
            $table->string('sport_type', 50);
            $table->time('opening_time')->default('06:00:00');
            $table->time('closing_time')->default('23:00:00');
            $table->unsignedBigInteger('default_hourly_rate');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['venue_id', 'sport_type', 'is_active'], 'idx_courts_search');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('venue_id')
                ->references('id')
                ->on('venues')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
        });

        Schema::dropIfExists('courts');
        Schema::dropIfExists('venues');
    }
};
