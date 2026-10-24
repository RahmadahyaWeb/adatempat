
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

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();

            $table->string('status', 32)->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();

            $table->unique(['business_id', 'slug']);

            $table->index(['business_id', 'status']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
