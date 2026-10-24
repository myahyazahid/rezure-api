<?php

namespace App\Console\Commands;

use App\Models\GithubReleaseDownload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Snapshots GitHub's cumulative download_count of every release asset once
 * a day (v4, Fase 4.12), so the Conversion page can compare all installer
 * downloads — including people who never visited the website — with the
 * ones GET /site/download recorded.
 */
#[Signature('app:sync-github-download-counts')]
#[Description('Snapshot the download count of every GitHub release asset')]
class SyncGithubDownloadCounts extends Command
{
    public function handle(): int
    {
        $repository = config('web-analytics.github_repository');

        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])
            ->when(config('services.github.token'), fn (PendingRequest $request, string $token) => $request->withToken($token))
            ->timeout(15)
            ->retry(2, 1000, throw: false)
            ->get("https://api.github.com/repos/{$repository}/releases", ['per_page' => 100]);

        if (! $response->successful()) {
            $this->error("GitHub releases request failed ({$response->status()}).");

            return self::FAILURE;
        }

        $date = now(config('app.display_timezone'))->toDateString();
        $assets = 0;

        foreach ($response->json() as $release) {
            foreach ($release['assets'] ?? [] as $asset) {
                GithubReleaseDownload::updateOrCreate(
                    ['date' => $date, 'asset_name' => mb_substr($asset['name'], 0, 255)],
                    [
                        'version' => mb_substr(ltrim((string) ($release['tag_name'] ?? ''), 'v'), 0, 32) ?: null,
                        'download_count' => (int) ($asset['download_count'] ?? 0),
                    ],
                );

                $assets++;
            }
        }

        $this->info("Snapshotted {$assets} release assets.");

        return self::SUCCESS;
    }
}
