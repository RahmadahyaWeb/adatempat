<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->ulid('public_id')->nullable()->unique()->after('id');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('google_id')->nullable()->unique()->after('phone');

            $table->string('status', 30)
                ->default('active')
                ->after('role');

            $table->text('mfa_secret')->nullable()->after('status');
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_secret');
            $table->timestamp('deactivated_at')->nullable()->after('mfa_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropUnique(['google_id']);

            $table->dropColumn([
                'public_id',
                'phone',
                'google_id',
                'status',
                'mfa_secret',
                'mfa_enabled_at',
                'deactivated_at',
            ]);
        });
    }
};
