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
        // One row per browser, keyed by the tracker's persistent visitor id
        // (v4). Website analytics is deliberately not privacy-restricted —
        // see docs/v4 — so the raw IP and city are kept here as-is.
        Schema::create('web_visitors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('visitor_uuid')->unique();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('pageviews_count')->default(0);

            // First-touch attribution: where this visitor came from the very
            // first time, kept even after later sessions arrive via other
            // channels.
            $table->string('first_landing_path', 512)->nullable();
            $table->string('first_referrer_host')->nullable();
            $table->string('first_channel', 32)->nullable();
            $table->string('first_source', 100)->nullable();
            $table->string('first_utm_source', 150)->nullable();
            $table->string('first_utm_medium', 150)->nullable();
            $table->string('first_utm_campaign', 150)->nullable();

            $table->string('last_ip_address', 45)->nullable();
            $table->char('last_country_code', 2)->nullable();
            $table->string('last_city', 100)->nullable();
            $table->string('last_browser', 64)->nullable();
            $table->string('last_os', 64)->nullable();
            $table->string('last_device_type', 32)->nullable();

            $table->timestamp('first_download_at')->nullable();

            // Website → desktop attribution (Fase 4.12): the install this
            // visitor most likely became, matched by IP + time window.
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('device_linked_at')->nullable();

            $table->timestamps();

            $table->index('first_seen_at');
            $table->index('last_seen_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_visitors');
    }
};
