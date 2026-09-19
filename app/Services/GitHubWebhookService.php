<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\BlogBuildLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubWebhookService
{
    /**
     * Dispatch the 'blog-updated' repository_dispatch event to trigger
     * GitHub Actions build on the rezure-websites repository.
     */
    public function dispatchBlogUpdated(?Blog $blog = null, string $action = 'updated'): array
    {
        $token = config('services.github.token');
        $repo = config('services.github.repository', 'myahyazahid/rezure-websites');

        // Record build log in database
        $buildLog = BlogBuildLog::create([
            'blog_id' => $blog?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'status' => 'dispatched',
            'payload' => [
                'blog_title' => $blog?->title,
                'blog_slug' => $blog?->slug,
                'action' => $action,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        if (empty($token)) {
            $msg = 'GITHUB_TOKEN is not configured in .env. Skipping repository_dispatch.';
            Log::info("GitHubWebhookService: {$msg}");
            $buildLog->update([
                'status' => 'failed',
                'error_message' => $msg,
            ]);
            return ['success' => false, 'message' => $msg, 'log' => $buildLog];
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
                        'blog_id' => $blog?->id,
                        'blog_title' => $blog?->title,
                        'action' => $action,
                        'log_id' => $buildLog->id,
                    ],
                ]);

            $buildLog->update([
                'response_code' => $response->status(),
            ]);

            if ($response->successful()) {
                Log::info("GitHubWebhookService: Successfully dispatched 'blog-updated' event to {$repo}.");
                $buildLog->update(['status' => 'in_progress']);
                return ['success' => true, 'message' => 'Dispatched successfully to GitHub Actions.', 'log' => $buildLog];
            }

            $errorMsg = "GitHub API returned {$response->status()}: " . $response->body();
            Log::warning("GitHubWebhookService: Failed to dispatch event to {$repo}. {$errorMsg}");
            $buildLog->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);
            return ['success' => false, 'message' => $errorMsg, 'log' => $buildLog];
        } catch (\Throwable $e) {
            $errorMsg = "Exception while dispatching: " . $e->getMessage();
            Log::error("GitHubWebhookService: {$errorMsg}");
            $buildLog->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);
            return ['success' => false, 'message' => $errorMsg, 'log' => $buildLog];
        }
    }

    /**
     * Get recent workflow runs from GitHub Actions API
     */
    public function getLatestWorkflowRuns(int $limit = 5): array
    {
        $token = config('services.github.token');
        $repo = config('services.github.repository', 'myahyazahid/rezure-websites');

        if (empty($token)) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->timeout(8)
                ->get("https://api.github.com/repos/{$repo}/actions/runs", [
                    'per_page' => $limit,
                ]);

            if ($response->successful()) {
                return $response->json('workflow_runs') ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning("GitHubWebhookService: Failed to fetch workflow runs: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Get detailed jobs and steps for a specific workflow run
     */
    public function getWorkflowRunJobs(int|string $runId): array
    {
        $token = config('services.github.token');
        $repo = config('services.github.repository', 'myahyazahid/rezure-websites');

        if (empty($token)) {
            return [];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->timeout(8)
                ->get("https://api.github.com/repos/{$repo}/actions/runs/{$runId}/jobs");

            if ($response->successful()) {
                return $response->json('jobs') ?? [];
            }
        } catch (\Throwable $e) {
            Log::warning("GitHubWebhookService: Failed to fetch workflow jobs: " . $e->getMessage());
        }

        return [];
    }
}