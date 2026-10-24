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
        Schema::create('web_errors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('error_uuid')->unique();
            $table->foreignId('web_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('web_pageview_id')->nullable()->constrained()->nullOnDelete();

            // sha1(message|source|line) — what the Errors page groups by.
            $table->string('fingerprint', 40);
            $table->text('message');
            $table->string('source', 2048)->nullable();
            $table->unsignedInteger('line')->nullable();
            $table->unsignedInteger('column')->nullable();
            $table->text('stack')->nullable();
            $table->string('path', 512)->nullable();
            $table->string('browser', 64)->nullable();
            $table->string('browser_version', 32)->nullable();
            $table->string('os', 64)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['fingerprint', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_errors');
    }
};
