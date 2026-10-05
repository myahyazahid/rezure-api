<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The optional QRIS image lives on the single-row config rather than in
     * `donate_methods`: there is at most one, it is a file instead of a
     * link, and keeping it here means replacing or removing it bumps
     * `updated_at` (the donate endpoint's `Last-Modified`) with no extra work.
     * All four columns are set together or all null.
     */
    public function up(): void
    {
        Schema::table('donate_configs', function (Blueprint $table): void {
            $table->string('qris_path')->nullable()->after('message');
            $table->string('qris_format', 8)->nullable()->after('qris_path');
            $table->unsignedInteger('qris_size')->nullable()->after('qris_format');
            $table->string('qris_sha256', 64)->nullable()->after('qris_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donate_configs', function (Blueprint $table): void {
            $table->dropColumn(['qris_path', 'qris_format', 'qris_size', 'qris_sha256']);
        });
    }
};
