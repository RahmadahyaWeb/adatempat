<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('dispute_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->restrictOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->text('message');
            $table->dateTime('created_at');
            $table->index(['dispute_id', 'created_at']);
        });
        Schema::create('dispute_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispute_id')->constrained('disputes')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('file_path', 255);
            $table->string('original_name', 180);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('caption', 255)->nullable();
            $table->dateTime('created_at');
            $table->index(['dispute_id', 'created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('dispute_evidence');
        Schema::dropIfExists('dispute_messages');
    }
};
