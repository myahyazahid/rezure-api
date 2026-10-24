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
        Schema::create('web_daily_page_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('path', 512);
            $table->unsignedBigInteger('pageviews')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);
            $table->unsignedBigInteger('entries')->default(0);
            $table->unsignedBigInteger('exits')->default(0);
            $table->unsignedBigInteger('bounces')->default(0);
            $table->unsignedBigInteger('total_engaged_ms')->default(0);
            $table->unsignedBigInteger('total_scroll_pct')->default(0);
            $table->unsignedBigInteger('not_found')->default(0);
            $table->timestamps();

            $table->unique(['date', 'path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_daily_page_summary');
    }
};
