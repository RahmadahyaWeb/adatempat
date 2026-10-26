<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('disk', 30)->default('public');
            $table->string('file_path', 255);
            $table->string('mime_type', 60);
            $table->unsignedInteger('file_size_bytes');
            $table->string('alt_text', 180);
            $table->boolean('is_cover')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['venue_id', 'sort_order']);
            $table->index(['venue_id', 'is_cover']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_media');
    }
};
