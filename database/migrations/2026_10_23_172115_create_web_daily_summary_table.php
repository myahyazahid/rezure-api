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
        // Rolled up by app:generate-web-summaries per display-timezone date.
        // Only additive metrics are summed from here across days — unique
        // visitors over a range are always counted from web_sessions.
        Schema::create('web_daily_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('new_visitors')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('engaged_sessions')->default(0);
            $table->unsignedBigInteger('bounces')->default(0);
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('total_duration_seconds')->default(0);
            $table->unsignedBigInteger('total_engaged_seconds')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_daily_summary');
    }
};
