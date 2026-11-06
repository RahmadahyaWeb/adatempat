<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('city')->index();
            $table->text('address');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('cover_photo_path')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_published')->default(false);
            $table->string('approval_status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['approval_status', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
