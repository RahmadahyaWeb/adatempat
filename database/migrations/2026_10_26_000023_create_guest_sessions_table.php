<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table): void {
            $table->id();
            $table->char('session_token_hash', 64)->unique();
            $table->string('nickname', 80);
            $table->foreignId('converted_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('ip_hash', 64);
            $table->dateTime('expires_at');
            $table->dateTime('last_active_at');
            $table->timestamps();

            $table->index(['expires_at', 'last_active_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};
