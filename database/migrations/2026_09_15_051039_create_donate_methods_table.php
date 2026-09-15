<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces `donate_configs`' fixed `local`/`global`/`crypto` JSON arrays
     * with one row per donate method, so the dashboard can list, add, edit,
     * and remove them individually instead of editing a handful of
     * always-present blank rows. `donate_configs` keeps just `message`.
     */
    public function up(): void
    {
        Schema::create('donate_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('category');
            $table->string('preset')->nullable();
            $table->string('label');
            $table->string('url')->nullable();
            $table->string('symbol')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->index('category');
        });

        $this->backfillFromDonateConfigs();

        Schema::table('donate_configs', function (Blueprint $table): void {
            $table->dropColumn(['local', 'global', 'crypto']);
        });
    }

    /**
     * Carries over whatever's already configured — this table already has
     * live data in production (a message plus at least one crypto wallet)
     * — rather than resetting maintainers back to nothing.
     */
    private function backfillFromDonateConfigs(): void
    {
        foreach (DB::table('donate_configs')->get() as $config) {
            $now = now();

            foreach (['local', 'global'] as $category) {
                $links = json_decode($config->{$category} ?? '[]', true) ?: [];

                foreach ($links as $link) {
                    DB::table('donate_methods')->insert([
                        'category' => $category,
                        'preset' => 'custom',
                        'label' => $link['label'] ?? '',
                        'url' => $link['url'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $wallets = json_decode($config->crypto ?? '[]', true) ?: [];

            foreach ($wallets as $wallet) {
                DB::table('donate_methods')->insert([
                    'category' => 'crypto',
                    'preset' => 'custom',
                    'label' => $wallet['label'] ?? '',
                    'symbol' => $wallet['symbol'] ?? null,
                    'address' => $wallet['address'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('donate_configs', function (Blueprint $table): void {
            $table->json('local')->nullable();
            $table->json('global')->nullable();
            $table->json('crypto')->nullable();
        });

        Schema::dropIfExists('donate_methods');
    }
};
