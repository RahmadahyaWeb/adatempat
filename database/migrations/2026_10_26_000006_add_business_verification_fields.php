<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('city', 100)->nullable()->after('address');
            $table->string('province', 100)->nullable()->after('city');
            $table->text('rejection_reason')->nullable()->after('published_at');
            $table->timestamp('verification_submitted_at')->nullable()->after('rejection_reason');
            $table->timestamp('verification_reviewed_at')->nullable()->after('verification_submitted_at');
            $table->foreignId('verification_reviewed_by')
                ->nullable()
                ->after('verification_reviewed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verification_reviewed_by');
            $table->foreignId('verified_by')
                ->nullable()
                ->after('verified_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropForeign(['verification_reviewed_by']);
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'city',
                'province',
                'rejection_reason',
                'verification_submitted_at',
                'verification_reviewed_at',
                'verification_reviewed_by',
                'verified_at',
                'verified_by',
            ]);
        });
    }
};
