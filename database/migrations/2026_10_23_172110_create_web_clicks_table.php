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
        Schema::create('web_clicks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('click_uuid')->unique();
            $table->foreignId('web_pageview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('web_session_id')->constrained()->cascadeOnDelete();
            $table->string('path', 512);
            $table->decimal('x_pct', 5, 2);
            $table->unsignedInteger('y_px');
            $table->unsignedInteger('page_height');
            $table->unsignedSmallInteger('viewport_width');
            $table->string('viewport_bucket', 16);
            $table->string('selector')->nullable();
            $table->string('tag', 32)->nullable();
            $table->string('text', 120)->nullable();
            $table->string('href', 2048)->nullable();
            $table->boolean('is_rage')->default(false);
            $table->timestamp('occurred_at');
            $table->timestamps();

            // The click heatmap always asks for one page at one viewport size
            // over a date range.
            $table->index(['path', 'viewport_bucket', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_clicks');
    }
};
