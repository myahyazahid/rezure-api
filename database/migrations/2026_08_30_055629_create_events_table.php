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
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_event_id');
            $table->string('event_type');
            $table->string('event_name')->nullable();
            $table->string('app_version');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            // Idempotency: a retried send from an offline client carries the
            // same client_event_id, so the second insert is a no-op instead
            // of a duplicate row skewing feature-usage counts.
            $table->unique(['device_id', 'client_event_id']);
            $table->index(['event_type', 'created_at']);
            $table->index(['app_version', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
