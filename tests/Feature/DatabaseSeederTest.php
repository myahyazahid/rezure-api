<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seeded address is a maintainer's own email and changes freely, so these
 * assert the behaviour (one signable local account) rather than a literal.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_one_dashboard_user_outside_production(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_seeded_user_can_sign_in(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->post('/login', [
            'email' => User::sole()->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
    }

    public function test_running_it_twice_does_not_duplicate_the_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
    }

    /**
     * The dashboard is internet-facing in production — a seeded account with a
     * known password would be an open door. Invoked directly rather than via
     * $this->seed() so this covers the seeder's own guard, not the
     * confirmation prompt db:seed already applies in production.
     */
    public function test_it_seeds_nothing_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        (new DatabaseSeeder)->run();

        $this->assertDatabaseCount('users', 0);
    }
}
