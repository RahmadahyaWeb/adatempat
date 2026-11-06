<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // shortcut: FK spaces/bookings ditambahkan saat jalur booking nyata memakai ID asli (2.1).
        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->unsignedBigInteger('space_id');
            $table->unsignedBigInteger('schedule_version_id')->nullable();
            $table->date('slot_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['space_id', 'slot_date', 'start_time']);
            $table->index('booking_id');
            $table->index(['space_id', 'slot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slots');
    }
};
