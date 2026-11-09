<?php

declare(strict_types=1);

use App\Enums\SlotStatus;
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
        Schema::create('court_pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->string('day_type', 20);
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('price_per_hour');
            $table->string('tier_name', 100)->nullable();
            $table->timestamps();

            $table->index(['court_id', 'day_type', 'start_time', 'end_time'], 'idx_pricing_lookup');
        });

        Schema::create('court_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedBigInteger('price');
            $table->string('status', 50)->default(SlotStatus::Available->value);
            $table->dateTime('locked_until')->nullable();
            $table->string('block_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['court_id', 'date', 'start_time'], 'uq_court_slot');
            $table->index(['court_id', 'date', 'status', 'start_time', 'price'], 'idx_slots_availability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_slots');
        Schema::dropIfExists('court_pricing_rules');
    }
};
