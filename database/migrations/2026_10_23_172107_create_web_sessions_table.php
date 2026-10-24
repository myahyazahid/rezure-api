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
        // Wide on purpose: every dimension the dashboard filters or breaks
        // down by lives on the session, so a filtered report is one indexed
        // scan of this table rather than a join per dimension.
        Schema::create('web_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_uuid')->unique();
            $table->foreignId('web_visitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('engaged_seconds')->default(0);
            $table->unsignedInteger('pageviews_count')->default(0);
            $table->unsignedInteger('events_count')->default(0);
            $table->boolean('is_engaged')->default(false);
            $table->boolean('is_bounce')->default(true);
            $table->boolean('has_conversion')->default(false);
            $table->boolean('is_new_visitor')->default(false);
            $table->unsignedInteger('visit_number')->default(1);
            $table->string('entry_path', 512)->nullable();
            $table->string('exit_path', 512)->nullable();

            $table->string('referrer_url', 2048)->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('channel', 32)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('utm_source', 150)->nullable();
            $table->string('utm_medium', 150)->nullable();
            $table->string('utm_campaign', 150)->nullable();
            $table->string('utm_term', 150)->nullable();
            $table->string('utm_content', 150)->nullable();
            $table->string('click_id_type', 16)->nullable();
            $table->string('click_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('geo_timezone', 64)->nullable();

            $table->string('browser', 64)->nullable();
            $table->string('browser_version', 32)->nullable();
            $table->string('browser_engine', 32)->nullable();
            $table->string('os', 64)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('device_brand', 64)->nullable();
            $table->string('device_model', 100)->nullable();
            $table->text('user_agent')->nullable();

            $table->unsignedSmallInteger('screen_width')->nullable();
            $table->unsignedSmallInteger('screen_height')->nullable();
            $table->unsignedSmallInteger('viewport_width')->nullable();
            $table->unsignedSmallInteger('viewport_height')->nullable();
            $table->string('viewport_bucket', 16)->nullable();
            $table->decimal('pixel_ratio', 4, 2)->nullable();
            $table->string('language', 35)->nullable();
            $table->json('languages')->nullable();
            $table->string('accept_language')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('color_scheme', 8)->nullable();
            $table->string('site_theme', 8)->nullable();
            $table->string('connection', 16)->nullable();
            $table->unsignedTinyInteger('cpu_cores')->nullable();
            $table->decimal('memory_gb', 5, 2)->nullable();
            $table->unsignedTinyInteger('touch_points')->nullable();
            $table->boolean('do_not_track')->nullable();

            $table->timestamps();

            $table->index('started_at');
            $table->index(['web_visitor_id', 'started_at']);
            $table->index(['channel', 'started_at']);
            $table->index(['country_code', 'started_at']);
            $table->index(['device_type', 'started_at']);
            $table->index(['browser', 'started_at']);
            $table->index(['utm_campaign', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_sessions');
    }
};
