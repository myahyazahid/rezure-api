<?php

namespace Database\Factories;

use App\Models\Sticker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sticker>
 */
class StickerFactory extends Factory
{
    /**
     * The smallest valid SVG — what the factory's rows point at when a test
     * writes the file (`Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG)`).
     */
    public const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="#f9a8d4"/></svg>';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = Str::slug(fake()->unique()->words(2, true));

        return [
            'slug' => $slug,
            'name' => Str::headline($slug),
            'category' => 'girls',
            'format' => 'svg',
            'file_path' => "stickers/{$slug}.svg",
            'size' => strlen(self::SVG),
            'sha256' => hash('sha256', self::SVG),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
