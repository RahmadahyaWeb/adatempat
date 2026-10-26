<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('is_open')->default(false);
            $table->time('open_time');
            $table->time('close_time');
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'day_of_week']);
        });

        Schema::create('venue_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->enum('pricing_unit', ['hourly', 'session', 'daily']);
            $table->string('session_name', 80)->nullable();
            $table->time('session_start_time')->nullable();
            $table->time('session_end_time')->nullable();
            $table->unsignedSmallInteger('step_interval_minutes')->default(60);
            $table->unsignedSmallInteger('min_duration_units')->default(1);
            $table->unsignedSmallInteger('max_duration_units')->default(24);
            $table->unsignedBigInteger('price_amount');
            $table->unsignedBigInteger('weekend_price_amount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['venue_id', 'is_active']);
            $table->index(['venue_id', 'pricing_unit', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_prices');
        Schema::dropIfExists('venue_availability_rules');
    }
};
