<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->foreignId('venue_template_id')
                ->nullable()
                ->after('category_id')
                ->constrained('venue_templates')
                ->nullOnDelete();
            $table->unsignedInteger('capacity_persons')->nullable()->after('description');
            $table->json('facilities_json')->nullable()->after('capacity_persons');
            $table->json('refund_policy_json')->nullable()->after('facilities_json');
        });

        Schema::create('venue_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->foreignId('template_field_id')->nullable()->constrained('template_fields')->restrictOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 15, 2)->nullable()->index();
            $table->boolean('value_boolean')->nullable()->index();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->unique(['venue_id', 'template_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_field_values');

        Schema::table('venues', function (Blueprint $table): void {
            $table->dropForeign(['venue_template_id']);
            $table->dropColumn([
                'venue_template_id',
                'capacity_persons',
                'facilities_json',
                'refund_policy_json',
            ]);
        });
    }
};
