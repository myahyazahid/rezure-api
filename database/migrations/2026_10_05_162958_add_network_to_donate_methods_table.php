<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which blockchain a crypto wallet's address lives on (`Tron (TRC-20)`,
     * `Ethereum (ERC-20)`…). The same coin exists on several networks and an
     * address sent on the wrong one is usually lost, so it can't live only
     * inside the label. Nullable because wallets saved before this column
     * existed have none, and because it means nothing for local/global links.
     */
    public function up(): void
    {
        Schema::table('donate_methods', function (Blueprint $table): void {
            $table->string('network', 60)->nullable()->after('symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donate_methods', function (Blueprint $table): void {
            $table->dropColumn('network');
        });
    }
};
