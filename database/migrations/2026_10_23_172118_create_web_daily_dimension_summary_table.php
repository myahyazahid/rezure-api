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
        // One generic table for every session dimension (channel, country,
        // browser, ...) instead of a summary table per dimension.
        Schema::create('web_daily_dimension_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('dimension', 32);
            $table->string('value');
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('bounces')->default(0);
            $table->unsignedBigInteger('total_duration_seconds')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();

            $table->unique(['date', 'dimension', 'value']);
            $table->index(['dimension', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_daily_dimension_summary');
    }
};
