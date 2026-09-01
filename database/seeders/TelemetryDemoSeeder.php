<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills devices/events/device_sessions with plausible-looking data so the
 * dashboard has something to chart in local dev. Not wired into
 * DatabaseSeeder::run() by default — run explicitly with
 * `php artisan db:seed --class=TelemetryDemoSeeder` since production never
 * wants fabricated telemetry mixed with real ingest.
 */
class TelemetryDemoSeeder extends Seeder
{
    private const VERSIONS = ['1.4.0', '1.4.0', '1.4.0', '1.3.2', '1.3.2', '1.3.1', '1.2.0'];

    private const OS_LIST = ['Windows 11', 'Windows 11', 'Windows 11', 'Windows 10', 'Windows 10', 'Other / unknown'];

    /** @var array<string, list<string>> */
    private const FEATURES = [
        'service.start' => ['nginx', 'apache', 'mysql', 'mariadb', 'php-fpm'],
        'service.stop' => ['nginx', 'apache', 'mysql', 'mariadb', 'php-fpm'],
        'project.create' => ['laravel', 'wordpress', 'vue-starter', 'static'],
        'runtime.switch' => ['php 8.3.2', 'php 8.2.15', 'php 8.1.27'],
        'tunnel.start' => ['cloudflared', 'ngrok'],
        'https.enable' => ['mkcert'],
    ];

    private const ERRORS = [
        'PortBindException',
        'ServiceStartTimeout',
        'ConfigParseError',
        'DatabaseConnectionRefused',
        'HostsFileWriteDenied',
    ];

    public function run(): void
    {
        $now = now();

        $this->command?->getOutput()->writeln('Seeding devices...');
        $deviceIds = $this->seedDevices($now);

        $this->command?->getOutput()->writeln('Seeding events...');
        $this->seedEvents($deviceIds, $now);

        $this->command?->getOutput()->writeln('Seeding sessions...');
        $this->seedSessions($deviceIds, $now);

        $this->command?->getOutput()->writeln('Seeding releases...');
        $this->seedReleases($now);
    }

    /**
     * @return list<int>
     */
    private function seedDevices(Carbon $now): array
    {
        $rows = [];

        for ($i = 0; $i < 1600; $i++) {
            $firstSeen = $now->copy()->subDays(fake()->numberBetween(1, 180))->subMinutes(fake()->numberBetween(0, 1440));

            // Weighted recency: most devices are still active, a tail has gone quiet.
            $recencyRoll = fake()->numberBetween(1, 100);
            $lastSeen = match (true) {
                $recencyRoll <= 55 => $now->copy()->subMinutes(fake()->numberBetween(0, 60 * 24)),
                $recencyRoll <= 80 => $now->copy()->subDays(fake()->numberBetween(1, 7)),
                $recencyRoll <= 95 => $now->copy()->subDays(fake()->numberBetween(8, 30)),
                default => $now->copy()->subDays(fake()->numberBetween(31, 90)),
            };

            if ($lastSeen->lt($firstSeen)) {
                $lastSeen = $firstSeen->copy();
            }

            $rows[] = [
                'device_id' => (string) Str::uuid(),
                'app_version' => fake()->randomElement(self::VERSIONS),
                'os' => fake()->randomElement(self::OS_LIST),
                'os_version' => fake()->randomElement(['23H2', '22H2', '21H2']),
                'telemetry_opted_out' => false,
                'first_seen_at' => $firstSeen,
                'last_seen_at' => $lastSeen,
                'created_at' => $firstSeen,
                'updated_at' => $lastSeen,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('devices')->insert($chunk);
        }

        return DB::table('devices')->pluck('id')->all();
    }

    /**
     * @param  list<int>  $deviceIds
     */
    private function seedEvents(array $deviceIds, Carbon $now): void
    {
        $eventTypes = array_keys(self::FEATURES);
        $rows = [];

        // 12 weeks of history with a gentle upward trend, mirroring adoption growth.
        for ($daysAgo = 83; $daysAgo >= 0; $daysAgo--) {
            $week = intdiv(83 - $daysAgo, 7) + 1;
            $eventsToday = fake()->numberBetween(150, 150 + $week * 40);

            for ($j = 0; $j < $eventsToday; $j++) {
                $occurredAt = $now->copy()->subDays($daysAgo)->subMinutes(fake()->numberBetween(0, 1439));
                $isError = fake()->boolean(3);

                if ($isError) {
                    $type = 'error.report';
                    $name = fake()->randomElement(self::ERRORS);
                } else {
                    $type = fake()->randomElement($eventTypes);
                    $name = fake()->randomElement(self::FEATURES[$type]);
                }

                $rows[] = [
                    'device_id' => fake()->randomElement($deviceIds),
                    'client_event_id' => (string) Str::uuid(),
                    'event_type' => $type,
                    'event_name' => $name,
                    'app_version' => fake()->randomElement(self::VERSIONS),
                    'payload' => null,
                    'occurred_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ];

                if (count($rows) >= 500) {
                    DB::table('events')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('events')->insert($rows);
        }
    }

    /**
     * @param  list<int>  $deviceIds
     */
    private function seedSessions(array $deviceIds, Carbon $now): void
    {
        $rows = [];

        foreach ($deviceIds as $deviceId) {
            $sessionCount = fake()->numberBetween(1, 6);

            for ($s = 0; $s < $sessionCount; $s++) {
                $startedAt = $now->copy()->subDays(fake()->numberBetween(0, 60))->subMinutes(fake()->numberBetween(0, 1439));
                $durationSeconds = fake()->numberBetween(120, 6 * 3600);
                $endedAt = $startedAt->copy()->addSeconds($durationSeconds);

                // A handful of sessions are still "open" so the dashboard has something live to show.
                $isOpen = $endedAt->gt($now) || fake()->boolean(2);

                $rows[] = [
                    'device_id' => $deviceId,
                    'client_session_id' => (string) Str::uuid(),
                    'app_version' => fake()->randomElement(self::VERSIONS),
                    'started_at' => $startedAt,
                    'last_heartbeat_at' => $isOpen ? $now->copy()->subMinutes(fake()->numberBetween(0, 5)) : $endedAt,
                    'ended_at' => $isOpen ? null : $endedAt,
                    'duration_seconds' => $isOpen ? null : $durationSeconds,
                    'created_at' => $startedAt,
                    'updated_at' => $isOpen ? $now : $endedAt,
                ];

                if (count($rows) >= 500) {
                    DB::table('device_sessions')->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            DB::table('device_sessions')->insert($rows);
        }
    }

    /**
     * Release history matching the version spread already used above, so
     * the Releases page and the Versions adoption chart tell the same story.
     */
    private function seedReleases(Carbon $now): void
    {
        $releases = [
            ['version' => '1.2.0', 'notes' => 'Initial public beta.', 'daysAgo' => 70],
            ['version' => '1.3.1', 'notes' => 'Added PHP version switcher and quick app installer templates.', 'daysAgo' => 45],
            ['version' => '1.3.2', 'notes' => 'Fixed a port-conflict detector false positive on Windows 11.', 'daysAgo' => 20],
            ['version' => '1.4.0', 'notes' => 'Added Cloudflare tunnel support and mkcert auto-HTTPS.', 'daysAgo' => 3],
        ];

        DB::table('releases')->insert(array_map(fn (array $release): array => [
            'version' => $release['version'],
            'notes' => $release['notes'],
            'published_at' => $now->copy()->subDays($release['daysAgo']),
            'created_at' => $now->copy()->subDays($release['daysAgo']),
            'updated_at' => $now->copy()->subDays($release['daysAgo']),
        ], $releases));
    }
}
