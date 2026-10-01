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
            // Geography counts unique devices per country over a date range
            // straight from events (per-day summary rows can't be summed into
            // a unique count). Leading with occurred_at also serves the
            // active-device and daily-summary range scans; device_id makes it
            // covering for the COUNT(DISTINCT device_id).
            $table->index(['occurred_at', 'country_code', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['occurred_at', 'country_code', 'device_id']);
        });
    }
};
