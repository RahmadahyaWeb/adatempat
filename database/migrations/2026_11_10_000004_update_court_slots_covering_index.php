<?php

declare(strict_types=1);

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
        Schema::table('court_slots', function (Blueprint $table) {
            $table->dropIndex('idx_slots_availability');
            $table->index(
                ['court_id', 'date', 'status', 'start_time', 'end_time', 'price', 'locked_until'],
                'idx_slots_availability'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('court_slots', function (Blueprint $table) {
            $table->dropIndex('idx_slots_availability');
            $table->index(
                ['court_id', 'date', 'status', 'start_time', 'price'],
                'idx_slots_availability'
            );
        });
    }
};
