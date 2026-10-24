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
        // The one desktop-telemetry exception to "never store the IP" (v3),
        // approved for Fase 4.12: only the IP a device was first seen from,
        // matched against website download IPs to attribute installs.
        Schema::table('devices', function (Blueprint $table): void {
            $table->string('first_seen_ip', 45)->nullable()->after('first_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('first_seen_ip');
        });
    }
};
