<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One row per sticker offered by the desktop client's Decorations →
     * Browse page. The image itself lives on the private `local` disk at
     * `file_path` and is only ever served through `GET /api/v1/stickers/
     * {slug}/file`, so its headers stay under our control. `sha256` and
     * `size` are stored rather than recomputed: the catalog advertises them
     * so the client can verify what it downloaded.
     */
    public function up(): void
    {
        Schema::create('stickers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 100);
            $table->string('category', 40)->default('general');
            $table->string('format', 8);
            $table->string('file_path');
            $table->unsignedInteger('size');
            $table->char('sha256', 64);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stickers');
    }
};
