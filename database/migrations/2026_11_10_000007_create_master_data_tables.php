<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_sport_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('province')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial approved sports and Indonesian cities/districts
        $now = now();
        DB::table('master_sport_types')->insert([
            ['slug' => 'badminton', 'name' => 'Badminton', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'padel', 'name' => 'Padel', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'futsal', 'name' => 'Futsal', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'tennis', 'name' => 'Tennis', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'mini_soccer', 'name' => 'Mini Soccer', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('master_cities')->insert([
            ['name' => 'Jakarta Pusat', 'province' => 'DKI Jakarta', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jakarta Selatan', 'province' => 'DKI Jakarta', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jakarta Barat', 'province' => 'DKI Jakarta', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jakarta Timur', 'province' => 'DKI Jakarta', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Jakarta Utara', 'province' => 'DKI Jakarta', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Surabaya', 'province' => 'Jawa Timur', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Bandung', 'province' => 'Jawa Barat', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Medan', 'province' => 'Sumatera Utara', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Denpasar', 'province' => 'Bali', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Semarang', 'province' => 'Jawa Tengah', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Yogyakarta', 'province' => 'DI Yogyakarta', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_cities');
        Schema::dropIfExists('master_sport_types');
    }
};
