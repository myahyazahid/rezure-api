<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single-row config, not an append-only history like `releases` —
     * there's no adoption/history angle to "what the donate buttons show
     * right now". See `App\Models\DonateConfig::current()`.
     */
    public function up(): void
    {
        Schema::create('donate_configs', function (Blueprint $table): void {
            $table->id();
            $table->text('message');
            $table->json('local');
            $table->json('global');
            $table->json('crypto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donate_configs');
    }
};
