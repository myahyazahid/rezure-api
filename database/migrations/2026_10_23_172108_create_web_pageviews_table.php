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
        Schema::create('web_pageviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('pageview_uuid')->unique();
            $table->foreignId('web_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('web_visitor_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('path', 512);
            $table->string('query_string', 1024)->nullable();
            $table->string('hash')->nullable();
            $table->string('title')->nullable();
            $table->string('referrer_url', 2048)->nullable();
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamp('occurred_at');

            // Filled in later by the engagement/vitals hits for this pageview.
            $table->unsignedInteger('engaged_ms')->default(0);
            $table->unsignedTinyInteger('max_scroll_pct')->default(0);
            $table->json('scroll_milestones')->nullable();
            $table->boolean('is_entry')->default(false);
            $table->boolean('is_exit')->default(false);
            $table->boolean('is_not_found')->default(false);
            $table->unsignedInteger('lcp_ms')->nullable();
            $table->unsignedInteger('inp_ms')->nullable();
            $table->decimal('cls', 8, 4)->nullable();
            $table->unsignedInteger('fcp_ms')->nullable();
            $table->unsignedInteger('ttfb_ms')->nullable();
            $table->unsignedInteger('dom_load_ms')->nullable();
            $table->unsignedInteger('page_load_ms')->nullable();

            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['path', 'occurred_at']);
            $table->index(['web_session_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_pageviews');
    }
};
