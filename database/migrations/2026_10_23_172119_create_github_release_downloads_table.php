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
        // Daily snapshot of GitHub's cumulative per-asset download_count, so
        // downloads that never touched the website (direct GitHub links) can
        // be compared against the ones /site/download recorded.
        Schema::create('github_release_downloads', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('version', 32)->nullable();
            $table->string('asset_name');
            $table->unsignedBigInteger('download_count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'asset_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('github_release_downloads');
    }
};
