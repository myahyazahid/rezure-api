<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 2.7 hardening: prove ingestion endpoints stay responsive even when
 * the queue is backed up (a slow or unavailable worker). phpunit.xml pins
 * QUEUE_CONNECTION=sync so every other feature test can assert on job side
 * effects directly — these tests opt back into the real 'database' driver,
 * the one actually used outside testing (config/queue.php), since 'sync'
 * would execute the job inline and defeat the point being proven here.
 */
class QueueResilienceTest extends TestCase
{
    use RefreshDatabase;

    private const BACKLOG_SIZE = 500;

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
    }

    /**
     * Fills the `jobs` table as if a slow/stalled worker had left hundreds
     * of jobs undrained, so a dispatch during the test lands behind a real
     * backlog rather than an empty table.
     */
    private function seedJobsBacklog(): void
    {
        $rows = array_map(fn (): array => [
            'queue' => 'default',
            // '__synthetic_backlog' is a marker unique to these placeholder
            // rows — a real job payload never contains it — so it's safe to
            // find-and-remove just the backlog without touching a real,
            // genuinely-dispatched job sharing the same table.
            'payload' => json_encode(['__synthetic_backlog' => true]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ], range(1, self::BACKLOG_SIZE));

        DB::table('jobs')->insert($rows);
    }

    public function test_heartbeat_endpoint_responds_immediately_despite_a_large_queue_backlog(): void
    {
        $this->seedJobsBacklog();
        $deviceId = fake()->uuid();

        $start = microtime(true);

        $response = $this->postJson('/api/v1/telemetry/heartbeat', [
            'device_id' => $deviceId,
            'session_id' => fake()->uuid(),
            'app_version' => '1.4.0',
        ]);

        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $this->assertLessThan(1000, $elapsedMs, 'Ingestion endpoint should respond in well under a second regardless of queue backlog.');

        // The job was only pushed, not run inline — the backlog (or a slow
        // worker) never delays the client's response, and processing is
        // genuinely deferred rather than happening synchronously anyway.
        $this->assertSame(self::BACKLOG_SIZE + 1, DB::table('jobs')->count());
        $this->assertDatabaseMissing('devices', ['device_id' => $deviceId]);
    }

    public function test_event_endpoint_responds_immediately_despite_a_large_queue_backlog(): void
    {
        $this->seedJobsBacklog();

        $start = microtime(true);

        $response = $this->postJson('/api/v1/telemetry/event', [
            'device_id' => fake()->uuid(),
            'event_id' => fake()->uuid(),
            'event_type' => 'service.start',
            'app_version' => '1.4.0',
        ]);

        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $this->assertLessThan(1000, $elapsedMs, 'Ingestion endpoint should respond in well under a second regardless of queue backlog.');
        $this->assertSame(self::BACKLOG_SIZE + 1, DB::table('jobs')->count());
        $this->assertDatabaseMissing('events', ['event_type' => 'service.start']);
    }

    public function test_support_ticket_endpoint_responds_immediately_despite_a_large_queue_backlog(): void
    {
        $this->seedJobsBacklog();

        $start = microtime(true);

        $response = $this->postJson('/api/v1/support/tickets', [
            'device_id' => fake()->uuid(),
            'client_ticket_id' => fake()->uuid(),
            'category' => 'bug',
            'title' => 'Responsiveness under backlog',
            'description' => 'Queued while the worker is behind.',
            'app_version' => '1.4.0',
        ]);

        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(202);
        $this->assertLessThan(1000, $elapsedMs, 'Ingestion endpoint should respond in well under a second regardless of queue backlog.');
        $this->assertSame(self::BACKLOG_SIZE + 1, DB::table('jobs')->count());
        $this->assertDatabaseMissing('tickets', ['title' => 'Responsiveness under backlog']);
    }

    public function test_a_stalled_worker_still_lets_a_later_manual_run_drain_the_backlog(): void
    {
        $this->seedJobsBacklog();

        $this->postJson('/api/v1/telemetry/heartbeat', [
            'device_id' => $deviceId = fake()->uuid(),
            'session_id' => fake()->uuid(),
            'app_version' => '1.4.0',
        ])->assertStatus(202);

        $this->assertDatabaseMissing('devices', ['device_id' => $deviceId]);

        // Drop the synthetic backlog rows (undecodable payloads) so the
        // worker only processes the one real job dispatched above.
        DB::table('jobs')->where('payload', 'like', '%__synthetic_backlog%')->delete();

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertDatabaseHas('devices', ['device_id' => $deviceId]);
    }
}
