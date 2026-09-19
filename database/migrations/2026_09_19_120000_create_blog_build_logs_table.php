<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_build_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_id')->nullable()->constrained('blogs')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('status')->default('dispatched');
            $table->unsignedInteger('response_code')->nullable();
            $table->string('github_run_id')->nullable();
            $table->string('github_run_url')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_build_logs');
    }
};