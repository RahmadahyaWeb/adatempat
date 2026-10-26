<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('guest_session_id')->nullable()->constrained('guest_sessions')->restrictOnDelete();
            $table->enum('channel', ['database', 'email', 'whatsapp']);
            $table->string('notification_type', 100);
            $table->string('title', 180)->nullable();
            $table->text('body')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('deduplication_key', 160)->unique();
            $table->enum('status', ['queued', 'processing', 'sent', 'failed', 'cancelled'])->default('queued');
            $table->dateTime('available_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
            $table->index(['recipient_user_id', 'status', 'created_at'], 'notifications_user_status_created_idx');
            $table->index(['guest_session_id', 'status', 'created_at'], 'notifications_guest_status_created_idx');
            $table->index(['status', 'available_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_recipient_identity_check CHECK ((recipient_user_id IS NOT NULL AND guest_session_id IS NULL) OR (recipient_user_id IS NULL AND guest_session_id IS NOT NULL))');
        }

        Schema::create('notification_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('provider', 80);
            $table->enum('status', ['started', 'succeeded', 'failed']);
            $table->string('provider_message_id', 160)->nullable();
            $table->json('request_metadata_json')->nullable();
            $table->json('response_metadata_json')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('created_at');
            $table->unique(['notification_id', 'attempt_number']);
            $table->index(['notification_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_attempts');
        Schema::dropIfExists('notifications');
    }
};
