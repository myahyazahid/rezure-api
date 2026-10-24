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
        Schema::create('web_funnels', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);

            // Ordered list of {type: event|pageview, match, label}.
            $table->json('steps');

            // 'session': every step inside one session. 'visitor': steps may
            // span sessions, within window_hours of the first step.
            $table->string('scope', 16)->default('session');
            $table->unsignedInteger('window_hours')->default(24);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_funnels');
    }
};
