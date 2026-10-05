<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * An optional icon for a donate method — a coin logo on a crypto wallet,
     * GitHub's mark on a link, and so on. The four columns are set together
     * or all null, same as the QRIS on `donate_configs`.
     */
    public function up(): void
    {
        Schema::table('donate_methods', function (Blueprint $table): void {
            $table->string('icon_path')->nullable()->after('address');
            $table->string('icon_format', 8)->nullable()->after('icon_path');
            $table->unsignedInteger('icon_size')->nullable()->after('icon_format');
            $table->string('icon_sha256', 64)->nullable()->after('icon_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donate_methods', function (Blueprint $table): void {
            $table->dropColumn(['icon_path', 'icon_format', 'icon_size', 'icon_sha256']);
        });
    }
};
