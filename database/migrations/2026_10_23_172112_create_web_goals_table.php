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
        Schema::create('web_goals', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);

            // 'event' matches web_events.name (+ optional label), 'pageview'
            // matches web_pageviews.path against a `*` wildcard pattern.
            $table->string('type', 16);
            $table->string('match', 512);
            $table->string('label_match')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_goals');
    }
};
