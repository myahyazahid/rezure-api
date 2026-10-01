<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single-row config, like `donate_configs` — there's only ever one
     * "a newer major is out" announcement live at a time. See
     * `App\Models\UpgradeNotice::current()`.
     */
    public function up(): void
    {
        Schema::create('upgrade_notices', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('major')->nullable();
            $table->text('message')->nullable();
            $table->string('url', 2048)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upgrade_notices');
    }
};
