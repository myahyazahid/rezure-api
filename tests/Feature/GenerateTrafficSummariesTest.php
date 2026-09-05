<?php

namespace Tests\Feature;

use App\Models\CountryTrafficSummary;
use App\Models\Device;
use App\Models\Event;
use App\Models\HourlyTrafficSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GenerateTrafficSummariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rolls_up_hourly_event_counts_for_the_given_date(): void
    {
        $device = Device::factory()->create();

        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-30 09:15:00')]);
        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-30 09:45:00')]);
        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-30 14:00:00')]);
        // Outside the target date — must not be counted.
        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-31 09:00:00')]);

        $this->artisan('app:generate-traffic-summaries', ['--date' => '2026-08-30'])->assertSuccessful();

        $this->assertSame(2, HourlyTrafficSummary::where('date', '2026-08-30')->where('hour', 9)->value('event_count'));
        $this->assertSame(1, HourlyTrafficSummary::where('date', '2026-08-30')->where('hour', 14)->value('event_count'));
        $this->assertSame(0, HourlyTrafficSummary::where('date', '2026-08-30')->where('hour', 0)->value('event_count'));
        $this->assertSame(24, HourlyTrafficSummary::where('date', '2026-08-30')->count());
    }

    public function test_it_rolls_up_distinct_device_counts_per_country_for_the_given_date(): void
    {
        $idDeviceOne = Device::factory()->create();
        $idDeviceTwo = Device::factory()->create();
        $usDevice = Device::factory()->create();

        Event::factory()->for($idDeviceOne)->create(['occurred_at' => Carbon::parse('2026-08-30 09:00:00'), 'country_code' => 'ID']);
        Event::factory()->for($idDeviceOne)->create(['occurred_at' => Carbon::parse('2026-08-30 10:00:00'), 'country_code' => 'ID']);
        Event::factory()->for($idDeviceTwo)->create(['occurred_at' => Carbon::parse('2026-08-30 11:00:00'), 'country_code' => 'ID']);
        Event::factory()->for($usDevice)->create(['occurred_at' => Carbon::parse('2026-08-30 12:00:00'), 'country_code' => 'US']);
        Event::factory()->for($usDevice)->create(['occurred_at' => Carbon::parse('2026-08-30 13:00:00'), 'country_code' => null]);

        $this->artisan('app:generate-traffic-summaries', ['--date' => '2026-08-30'])->assertSuccessful();

        $this->assertSame(2, CountryTrafficSummary::where('date', '2026-08-30')->where('country_code', 'ID')->value('device_count'));
        $this->assertSame(1, CountryTrafficSummary::where('date', '2026-08-30')->where('country_code', 'US')->value('device_count'));
        $this->assertSame(2, CountryTrafficSummary::where('date', '2026-08-30')->count());
    }

    public function test_it_defaults_to_summarizing_yesterday(): void
    {
        $device = Device::factory()->create();
        $yesterday = now()->subDay();

        Event::factory()->for($device)->create(['occurred_at' => $yesterday->copy()->setTime(8, 0)]);

        $this->artisan('app:generate-traffic-summaries')->assertSuccessful();

        $this->assertSame(
            1,
            HourlyTrafficSummary::where('date', $yesterday->toDateString())->where('hour', 8)->value('event_count'),
        );
    }

    public function test_rerunning_for_the_same_date_updates_instead_of_duplicating_rows(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-30 09:00:00'), 'country_code' => 'ID']);

        $this->artisan('app:generate-traffic-summaries', ['--date' => '2026-08-30'])->assertSuccessful();
        $this->artisan('app:generate-traffic-summaries', ['--date' => '2026-08-30'])->assertSuccessful();

        $this->assertSame(24, HourlyTrafficSummary::where('date', '2026-08-30')->count());
        $this->assertSame(1, CountryTrafficSummary::where('date', '2026-08-30')->count());
    }
}
