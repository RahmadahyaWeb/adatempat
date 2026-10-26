<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name', 'version']);
            $table->index(['category_id', 'is_active']);
        });

        Schema::create('template_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_template_id')->constrained('venue_templates')->restrictOnDelete();
            $table->string('field_key', 80);
            $table->string('label', 120);
            $table->enum('input_type', ['text', 'textarea', 'number', 'boolean', 'select', 'multiselect']);
            $table->string('unit_label', 30)->nullable();
            $table->json('options_json')->nullable();
            $table->json('validation_rules_json')->nullable();
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['venue_template_id', 'field_key']);
            $table->index(['venue_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_fields');
        Schema::dropIfExists('venue_templates');
    }
};
