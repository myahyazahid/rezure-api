<?php

namespace App\Jobs;

use App\Services\WebAnalytics\SiteHitProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSiteHitBatchJob implements ShouldQueue
{
    use Queueable;

    /**
     * How many times hits waiting on their pageview are re-queued before
     * they're given up on.
     */
    private const MAX_ROUNDS = 3;

    /**
     * Runs on its own queue (`web` by default) so a burst of website traffic
     * never delays desktop telemetry on the default queue.
     *
     * @param  array<string, mixed>  $batch  validated SiteHitRequest payload
     * @param  array{ip: ?string, user_agent: ?string, accept_language: ?string, origin: ?string, received_at: string}  $request
     */
    public function __construct(
        private readonly array $batch,
        private readonly array $request,
        private readonly int $round = 1,
    ) {
        $this->onQueue(config('web-analytics.queue'));
    }

    public function handle(SiteHitProcessor $processor): void
    {
        $deferred = $processor->process($this->batch, $this->request);

        // An engagement/click/... hit can land in a batch processed before
        // the one carrying its pageview. Only those hits are retried, a bit
        // later, rather than failing (and re-running) the whole batch.
        if ($deferred !== [] && $this->round < self::MAX_ROUNDS) {
            self::dispatch([...$this->batch, 'hits' => $deferred], $this->request, $this->round + 1)
                ->delay(now()->addSeconds(30 * $this->round));
        }
    }
}
