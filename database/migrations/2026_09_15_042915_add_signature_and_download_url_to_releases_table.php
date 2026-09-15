<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backs the `windows-x86_64` entry of the Tauri updater manifest
     * `GET /version/latest` now returns. Both stay nullable — a release
     * published without them (pre-updater releases, or a changelog-only
     * republish) simply isn't advertised as an available platform, see
     * `VersionController`.
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->text('signature')->nullable()->after('notes');
            $table->string('download_url')->nullable()->after('signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->dropColumn(['signature', 'download_url']);
        });
    }
};
