<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('base_price', 12, 2);
            $table->boolean('instant_book')->default(true);
            $table->string('cancellation_policy')->default('free_cancellation');
            $table->unsignedInteger('cancellation_cutoff_hours')->default(6);
            $table->decimal('refund_percentage', 5, 2)->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['venue_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
