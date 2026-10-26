<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('label', 120);
            $table->enum('input_type', ['text', 'number', 'boolean', 'select']);
            $table->json('options_json')->nullable();
            $table->enum('moderation_status', ['active', 'hidden_by_admin'])->default('active');
            $table->text('moderation_reason')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['venue_id', 'field_key']);
            $table->index(['venue_id', 'moderation_status', 'sort_order']);
        });

        Schema::table('venue_field_values', function (Blueprint $table): void {
            $table->foreignId('owner_custom_field_id')
                ->nullable()
                ->after('template_field_id')
                ->constrained('owner_custom_fields')
                ->restrictOnDelete();

            $table->unique(['venue_id', 'owner_custom_field_id'], 'venue_field_values_venue_owner_field_unique');
        });
    }

    public function down(): void
    {
        Schema::table('venue_field_values', function (Blueprint $table): void {
            $table->dropUnique('venue_field_values_venue_owner_field_unique');
            $table->dropForeign(['owner_custom_field_id']);
            $table->dropColumn('owner_custom_field_id');
        });

        Schema::dropIfExists('owner_custom_fields');
    }
};
