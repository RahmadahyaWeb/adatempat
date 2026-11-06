<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->string('pricing_type');
            $table->unsignedInteger('stock_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['space_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_add_ons');
    }
};
