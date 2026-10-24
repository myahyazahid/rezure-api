<?php

namespace Database\Factories;

use App\Models\WebEvent;
use App\Models\WebSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebEvent>
 */
class WebEventFactory extends Factory
{
    protected $model = WebEvent::class;

    public function definition(): array
    {
        return [
            'event_uuid' => fake()->uuid(),
            'web_session_id' => WebSession::factory(),
            'web_visitor_id' => fn (array $attributes): ?int => WebSession::find($attributes['web_session_id'])?->web_visitor_id,
            'name' => 'outbound_click',
            'label' => 'https://github.com/myahyazahid/rezure',
            'path' => '/',
            'occurred_at' => fn (array $attributes) => WebSession::find($attributes['web_session_id'])?->started_at ?? now(),
        ];
    }

    public function download(string $version = '3.1.0'): static
    {
        return $this->state(fn (): array => ['name' => WebEvent::DOWNLOAD, 'label' => $version, 'path' => '/download']);
    }
}
