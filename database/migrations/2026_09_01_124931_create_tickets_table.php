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
        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();

            // Idempotency: a client retrying a multipart submission after a
            // dropped connection carries the same client_ticket_id, mirroring
            // client_event_id on events — the second insert is a no-op
            // instead of a duplicate ticket with duplicate attachments.
            $table->uuid('client_ticket_id');

            $table->string('category');
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('open');
            $table->string('app_version')->nullable();
            $table->string('os_version')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'client_ticket_id']);
            $table->index(['status', 'created_at']);
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
