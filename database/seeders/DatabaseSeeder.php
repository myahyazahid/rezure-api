<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedLocalDashboardUser();
    }

    /**
     * A known-credentials account so a fresh local checkout can sign in to the
     * dashboard immediately.
     *
     * Never runs in production: the dashboard is internet-facing once deployed,
     * and a predictable password would be an open door. Production accounts are
     * created with `php artisan app:create-dashboard-user` instead.
     */
    private function seedLocalDashboardUser(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping the local dashboard user — use app:create-dashboard-user in production.');

            return;
        }

        $email = 'myahyazahid11@gmail.com';

        // Guarded by an existence check, not create() — migrate --seed re-runs
        // this every time, and a plain create() collides on the unique email
        // constraint once this row already exists.
        if (User::where('email', $email)->doesntExist()) {
            User::create([
                'name' => 'Rezure Admin',
                'email' => $email,
                'password' => Hash::make('password'),
            ]);
        }

        $this->command?->info("Local dashboard login: {$email} / password");
    }
}
