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
        // One row per publish, not one row per version — republishing the
        // same version (e.g. to fix a typo in notes) is a new row, and
        // "current" is simply the most recent one. See docs/releases.md.
        Schema::create('releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version');
            $table->text('notes')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
