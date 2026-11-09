<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->default(UserRole::Player->value)->after('email')->index();
            } else {
                $table->string('role', 50)->default(UserRole::Player->value)->change();
            }

            if (! Schema::hasColumn('users', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable()->after('role')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'venue_id')) {
                $table->dropIndex(['venue_id']);
                $table->dropColumn('venue_id');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            }

            $table->string('password')->nullable(false)->change();
        });
    }
};
