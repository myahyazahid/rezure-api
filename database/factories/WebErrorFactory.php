<?php

namespace Database\Factories;

use App\Models\WebError;
use App\Models\WebSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebError>
 */
class WebErrorFactory extends Factory
{
    protected $model = WebError::class;

    public function definition(): array
    {
        $message = 'TypeError: Cannot read properties of undefined';

        return [
            'error_uuid' => fake()->uuid(),
            'web_session_id' => WebSession::factory(),
            'fingerprint' => WebError::fingerprintFor($message, 'https://rezure.redscale.my.id/assets/app.js', 12),
            'message' => $message,
            'source' => 'https://rezure.redscale.my.id/assets/app.js',
            'line' => 12,
            'column' => 8,
            'path' => '/',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'occurred_at' => fn (array $attributes) => WebSession::find($attributes['web_session_id'])->started_at,
        ];
    }
}
