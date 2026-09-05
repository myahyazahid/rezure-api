<?php

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
        Schema::table('events', function (Blueprint $table): void {
            // Resolved server-side from the request IP at ingest time — the
            // raw IP itself is never persisted, only the 2-letter result.
            $table->char('country_code', 2)->nullable()->after('occurred_at');
            $table->index('country_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['country_code']);
            $table->dropColumn('country_code');
        });
    }
};
