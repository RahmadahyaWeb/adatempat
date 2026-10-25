<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique()->after('id');
        });

        Schema::table('venues', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique()->after('id');
        });

        $this->backfillPublicIds('users');
        $this->backfillPublicIds('businesses');
        $this->backfillPublicIds('venues');
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropUnique('venues_public_id_unique');
            $table->dropColumn('public_id');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropUnique('businesses_public_id_unique');
            $table->dropColumn('public_id');
        });
    }

    private function backfillPublicIds(string $table): void
    {
        DB::table($table)
            ->select('id')
            ->whereNull('public_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $record) use ($table): void {
                DB::table($table)
                    ->where('id', $record->id)
                    ->whereNull('public_id')
                    ->update(['public_id' => (string) Str::ulid()]);
            });
    }
};
