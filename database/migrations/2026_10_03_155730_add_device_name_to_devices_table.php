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
        Schema::table('devices', function (Blueprint $table): void {
            // The Windows account name (%USERNAME%) the client reports on its
            // heartbeat, so the dashboard can tell installs apart by a human
            // name. Null for clients released before the field existed (3.0.0
            // and older), which never send it.
            $table->string('device_name', 64)->nullable()->after('device_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('device_name');
        });
    }
};
