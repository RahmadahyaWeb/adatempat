<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slot_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('space_id');
            $table->date('slot_date');
            $table->timestamps();

            $table->unique(['space_id', 'slot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slot_locks');
    }
};
