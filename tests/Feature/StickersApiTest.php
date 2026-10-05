<?php

namespace Tests\Feature;

use App\Models\Sticker;
use Database\Factories\StickerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StickersApiTest extends TestCase
{
    use RefreshDatabase;

    private function stickerWithFile(array $attributes = []): Sticker
    {
        $sticker = Sticker::factory()->create($attributes);
        Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG);

        return $sticker;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_the_catalog_is_empty_when_nothing_has_been_published(): void
    {
        $this->getJson('/api/v1/stickers')
            ->assertOk()
            ->assertExactJson(['stickers' => []]);
    }

    public function test_the_catalog_lists_published_stickers_newest_first_with_what_the_client_verifies(): void
    {
        $older = $this->stickerWithFile(['slug' => 'pink-bow', 'name' => 'Pink bow', 'category' => 'girls']);
        $newer = $this->stickerWithFile(['slug' => 'gamepad', 'name' => 'Gamepad', 'category' => 'mens']);
        Sticker::factory()->unpublished()->create(['slug' => 'draft']);

        $response = $this->getJson('/api/v1/stickers')->assertOk();

        $response->assertJsonCount(2, 'stickers');
        $response->assertJsonPath('stickers.0.id', 'gamepad');
        $response->assertJsonPath('stickers.1.id', 'pink-bow');
        $response->assertJsonPath('stickers.0', [
            'id' => 'gamepad',
            'name' => 'Gamepad',
            'category' => 'mens',
            'format' => 'svg',
            'size' => $newer->size,
            'sha256' => hash('sha256', StickerFactory::SVG),
            'url' => url('/api/v1/stickers/gamepad/file'),
        ]);
        $this->assertNotSame($older->id, $newer->id);
    }

    public function test_the_catalog_answers_not_modified_when_the_client_already_has_it(): void
    {
        $this->stickerWithFile();

        $etag = $this->getJson('/api/v1/stickers')->assertOk()->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $this->withHeader('If-None-Match', $etag)
            ->getJson('/api/v1/stickers')
            ->assertStatus(304);
    }

    public function test_the_catalog_changes_when_a_sticker_is_hidden_or_deleted(): void
    {
        $keep = $this->stickerWithFile();
        $gone = $this->stickerWithFile();

        $withBoth = $this->getJson('/api/v1/stickers')->headers->get('ETag');

        $gone->update(['is_published' => false]);
        $hidden = $this->getJson('/api/v1/stickers')->headers->get('ETag');
        $this->assertNotSame($withBoth, $hidden);

        $keep->delete();
        $this->assertNotSame($hidden, $this->getJson('/api/v1/stickers')->headers->get('ETag'));
    }

    public function test_a_file_is_served_with_its_own_type_and_the_headers_that_keep_an_svg_inert(): void
    {
        $sticker = $this->stickerWithFile(['slug' => 'pink-bow']);

        $response = $this->get('/api/v1/stickers/pink-bow/file')->assertOk();

        $response->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
        $response->assertHeader('ETag', '"'.$sticker->sha256.'"');
        $this->assertSame(StickerFactory::SVG, $response->streamedContent());
    }

    public function test_a_file_answers_not_modified_for_its_etag(): void
    {
        $sticker = $this->stickerWithFile(['slug' => 'pink-bow']);

        $this->withHeader('If-None-Match', '"'.$sticker->sha256.'"')
            ->get('/api/v1/stickers/pink-bow/file')
            ->assertStatus(304);
    }

    public function test_a_png_is_served_as_a_png(): void
    {
        $sticker = Sticker::factory()->create(['slug' => 'dot', 'format' => 'png', 'file_path' => 'stickers/dot.png']);
        Storage::disk('local')->put($sticker->file_path, "\x89PNG\r\n\x1a\n");

        $this->get('/api/v1/stickers/dot/file')->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_a_file_is_not_found_when_unpublished_unknown_or_missing_from_disk(): void
    {
        $this->stickerWithFile(['slug' => 'draft', 'is_published' => false]);
        Sticker::factory()->create(['slug' => 'lost']);

        $this->get('/api/v1/stickers/draft/file')->assertNotFound();
        $this->get('/api/v1/stickers/nope/file')->assertNotFound();
        $this->get('/api/v1/stickers/lost/file')->assertNotFound();
    }

    public function test_a_slug_that_is_not_a_slug_never_reaches_the_controller(): void
    {
        $this->get('/api/v1/stickers/..%2F..%2F.env/file')->assertNotFound();
        $this->get('/api/v1/stickers/UPPER/file')->assertNotFound();
    }

    /**
     * The Browse page asks for every preview at once, from an `<img>` that
     * can't send a device header — more than the global 30/minute per IP.
     */
    public function test_a_page_of_previews_is_not_cut_off_by_the_global_api_limit(): void
    {
        $this->stickerWithFile(['slug' => 'pink-bow']);

        foreach (range(1, 45) as $request) {
            $this->get('/api/v1/stickers/pink-bow/file')->assertOk();
        }
    }
}
