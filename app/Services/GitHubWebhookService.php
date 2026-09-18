<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubWebhookService
{
    /**
     * Dispatch the 'blog-updated' repository_dispatch event to trigger
     * GitHub Actions build on the rezure-websites repository.
     */
    public function dispatchBlogUpdated(): bool
    {
        $token = config('services.github.token');
        $repo = config('services.github.repository', 'myahyazahid/rezure-websites');

        if (empty($token)) {
            Log::info('GitHubWebhookService: GITHUB_TOKEN is not configured in .env. Skipping repository_dispatch.');
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->timeout(10)
                ->post("https://api.github.com/repos/{$repo}/dispatches", [
                    'event_type' => 'blog-updated',
                    'client_payload' => [
                        'timestamp' => now()->toIso8601String(),
                        'triggered_by' => 'dashboard',
                    ],
                ]);

            if ($response->successful()) {
                Log::info("GitHubWebhookService: Successfully dispatched 'blog-updated' event to {$repo}.");
                return true;
            }

            Log::warning("GitHubWebhookService: Failed to dispatch event to {$repo}. Status: {$response->status()}, Body: {$response->body()}");
            return false;
        } catch (\Throwable $e) {
            Log::error("GitHubWebhookService: Exception while dispatching event to {$repo}: {$e->getMessage()}");
            return false;
        }
    }
}
