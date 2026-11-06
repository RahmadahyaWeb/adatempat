<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_version_id')->constrained('space_schedule_versions')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('open_time');
            $table->time('close_time');
            $table->unsignedInteger('slot_duration_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['space_id', 'schedule_version_id', 'day_of_week', 'open_time'],
                'schedules_space_version_day_open_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
