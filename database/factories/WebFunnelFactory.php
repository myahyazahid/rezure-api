<?php

namespace Database\Factories;

use App\Models\WebFunnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebFunnel>
 */
class WebFunnelFactory extends Factory
{
    protected $model = WebFunnel::class;

    public function definition(): array
    {
        return [
            'name' => 'Landing to download',
            'steps' => [
                ['type' => 'pageview', 'match' => '/', 'label' => null],
                ['type' => 'pageview', 'match' => '/download', 'label' => null],
                ['type' => 'event', 'match' => 'download', 'label' => null],
            ],
            'scope' => WebFunnel::SCOPE_SESSION,
            'window_hours' => 24,
        ];
    }
}
