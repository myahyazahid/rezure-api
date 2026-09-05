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
        Schema::create('country_traffic_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->char('country_code', 2);
            $table->unsignedBigInteger('device_count')->default(0);
            $table->timestamps();

            // Regenerating a day's summary (e.g. a re-run after a backfill)
            // updates the same row instead of doubling the count.
            $table->unique(['date', 'country_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('country_traffic_summary');
    }
};
