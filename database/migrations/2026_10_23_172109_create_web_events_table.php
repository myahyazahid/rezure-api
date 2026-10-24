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
        Schema::create('web_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_uuid')->unique();

            // Nullable: a download recorded by GET /site/download from a link
            // that didn't carry the tracker's ids belongs to no session.
            $table->foreignId('web_session_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('web_visitor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('web_pageview_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 64);
            $table->string('label')->nullable();
            $table->json('props')->nullable();
            $table->string('path', 512)->nullable();

            // Only set for server-side downloads — what Fase 4.12 matches a
            // new desktop install's first IP against.
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['name', 'occurred_at']);
            $table->index(['name', 'label', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_events');
    }
};
