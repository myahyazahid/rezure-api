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
        Schema::create('hourly_traffic_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->unsignedBigInteger('event_count')->default(0);
            $table->timestamps();

            // Regenerating a day's summary (e.g. a re-run after a backfill)
            // updates the same row instead of doubling the count.
            $table->unique(['date', 'hour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hourly_traffic_summary');
    }
};
