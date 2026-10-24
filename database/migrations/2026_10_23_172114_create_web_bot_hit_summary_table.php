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
        // Bots are dropped at ingest and only counted here, so crawler
        // traffic stays visible without polluting visitor statistics.
        Schema::create('web_bot_hit_summary', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('bot_name', 100);
            $table->string('bot_category', 64)->nullable();
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamps();

            $table->unique(['date', 'bot_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_bot_hit_summary');
    }
};
