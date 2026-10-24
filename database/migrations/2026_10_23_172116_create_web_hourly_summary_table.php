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
        // Date and hour are both in the display timezone, like the daily
        // summary — not UTC, unlike the desktop hourly_traffic_summary.
        Schema::create('web_hourly_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->timestamps();

            $table->unique(['date', 'hour']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_hourly_summary');
    }
};
