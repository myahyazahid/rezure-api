<?php

namespace Tests\Feature;

use App\Models\Sticker;
use App\Models\User;
use Database\Factories\StickerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardStickersTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function svg(string $name = 'bow.svg', ?string $contents = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents ?? StickerFactory::SVG);
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $sticker = Sticker::factory()->create();

        $this->get('/dashboard/stickers')->assertRedirect(route('login'));
        $this->get("/dashboard/stickers/{$sticker->id}/preview")->assertRedirect(route('login'));
        $this->post('/dashboard/stickers', ['name' => 'x', 'file' => $this->svg()])->assertRedirect(route('login'));
        $this->delete("/dashboard/stickers/{$sticker->id}")->assertRedirect(route('login'));

        $this->assertDatabaseCount('stickers', 1);
    }

    public function test_the_list_shows_published_and_hidden_stickers(): void
    {
        $this->signIn();
        Sticker::factory()->create(['name' => 'Pink bow']);
        Sticker::factory()->unpublished()->create(['name' => 'Secret draft']);

        $this->get('/dashboard/stickers')
            ->assertOk()
            ->assertSee('Pink bow')
            ->assertSee('Secret draft')
            ->assertSee('Hidden');
    }

    public function test_an_svg_is_stored_under_its_slug_and_described_from_its_own_bytes(): void
    {
        $this->signIn();

        $this->post('/dashboard/stickers', [
            'name' => 'Pink Bow!',
            'category' => 'Girls',
            'is_published' => '1',
            'file' => $this->svg('whatever-the-browser-called-it.svg'),
        ])->assertRedirect(route('dashboard.stickers.index'));

        $sticker = Sticker::sole();
        $this->assertSame('pink-bow', $sticker->slug);
        $this->assertSame('girls', $sticker->category);
        $this->assertSame('svg', $sticker->format);
        $this->assertSame('stickers/pink-bow.svg', $sticker->file_path);
        $this->assertSame(strlen(StickerFactory::SVG), $sticker->size);
        $this->assertSame(hash('sha256', StickerFactory::SVG), $sticker->sha256);
        $this->assertTrue($sticker->is_published);
        Storage::disk('local')->assertExists('stickers/pink-bow.svg');
    }

    public function test_the_format_comes_from_the_bytes_not_the_file_name(): void
    {
        $this->signIn();

        $this->post('/dashboard/stickers', [
            'name' => 'Dot',
            'category' => 'general',
            'file' => UploadedFile::fake()->createWithContent('actually-a-png.svg', self::PNG),
        ])->assertRedirect();

        $this->assertSame('png', Sticker::sole()->format);
        Storage::disk('local')->assertExists('stickers/dot.png');
    }

    public function test_an_unchecked_published_box_saves_a_hidden_sticker(): void
    {
        $this->signIn();

        $this->post('/dashboard/stickers', [
            'name' => 'Draft',
            'category' => 'general',
            'is_published' => '0',
            'file' => $this->svg(),
        ])->assertRedirect();

        $this->assertFalse(Sticker::sole()->is_published);
    }

    public function test_two_stickers_with_the_same_name_get_different_ids(): void
    {
        $this->signIn();

        foreach ([1, 2, 3] as $ignored) {
            $this->post('/dashboard/stickers', ['name' => 'Heart', 'category' => 'girls', 'file' => $this->svg()]);
        }

        $this->assertSame(['heart', 'heart-2', 'heart-3'], Sticker::orderBy('id')->pluck('slug')->all());
    }

    public function test_a_category_typed_with_spaces_and_capitals_becomes_a_slug(): void
    {
        $this->signIn();

        $this->post('/dashboard/stickers', ['name' => 'A', 'category' => 'Pixel Art', 'file' => $this->svg()]);
        $this->post('/dashboard/stickers', ['name' => 'B', 'category' => '', 'file' => $this->svg()]);

        $this->assertSame(['pixel-art', 'general'], Sticker::orderBy('id')->pluck('category')->all());
    }

    /**
     * @return array<string, array{string, string}> file name, contents
     */
    public static function unusableUploads(): array
    {
        return [
            'an svg with a script' => ['a.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>x()</script></svg>'],
            'an svg with an event handler' => ['b.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="x()"/>'],
            'text named like a png' => ['c.png', 'just some text'],
            'a file over the size limit' => ['d.svg', StickerFactory::SVG.str_repeat(' ', Sticker::MAX_BYTES)],
        ];
    }

    #[DataProvider('unusableUploads')]
    public function test_an_upload_the_client_could_not_use_is_refused_and_nothing_is_stored(string $name, string $contents): void
    {
        $this->signIn();

        $this->post('/dashboard/stickers', [
            'name' => 'Bad',
            'category' => 'general',
            'file' => UploadedFile::fake()->createWithContent($name, $contents),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('stickers', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_new_sticker_needs_a_name_and_an_image(): void
    {
        $this->signIn();

        $this->post('/dashboard/stickers', ['category' => 'general'])
            ->assertSessionHasErrors(['name', 'file']);
    }

    public function test_editing_without_a_file_keeps_the_image_and_the_id(): void
    {
        $this->signIn();
        $sticker = Sticker::factory()->create(['slug' => 'pink-bow', 'name' => 'Old']);
        Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG);

        $this->put("/dashboard/stickers/{$sticker->id}", [
            'name' => 'Brand new name',
            'category' => 'mens',
            'is_published' => '1',
        ])->assertRedirect(route('dashboard.stickers.index'));

        $sticker->refresh();
        $this->assertSame('pink-bow', $sticker->slug);
        $this->assertSame('Brand new name', $sticker->name);
        $this->assertSame('mens', $sticker->category);
        $this->assertSame(hash('sha256', StickerFactory::SVG), $sticker->sha256);
        Storage::disk('local')->assertExists($sticker->file_path);
    }

    public function test_replacing_the_image_changes_its_hash_and_removes_the_old_file(): void
    {
        $this->signIn();
        $sticker = Sticker::factory()->create(['slug' => 'pink-bow']);
        Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG);

        $this->put("/dashboard/stickers/{$sticker->id}", [
            'name' => $sticker->name,
            'category' => 'girls',
            'is_published' => '1',
            'file' => UploadedFile::fake()->createWithContent('new.png', self::PNG),
        ])->assertRedirect();

        $sticker->refresh();
        $this->assertSame('pink-bow', $sticker->slug);
        $this->assertSame('png', $sticker->format);
        $this->assertSame(hash('sha256', self::PNG), $sticker->sha256);
        Storage::disk('local')->assertExists('stickers/pink-bow.png');
        Storage::disk('local')->assertMissing('stickers/pink-bow.svg');
    }

    public function test_hiding_and_publishing_toggle_what_the_catalog_offers(): void
    {
        $this->signIn();
        $sticker = Sticker::factory()->create();
        Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG);

        $this->patch("/dashboard/stickers/{$sticker->id}/toggle-publish")->assertRedirect();
        $this->assertFalse($sticker->fresh()->is_published);
        $this->getJson('/api/v1/stickers')->assertJsonCount(0, 'stickers');

        $this->patch("/dashboard/stickers/{$sticker->id}/toggle-publish");
        $this->getJson('/api/v1/stickers')->assertJsonCount(1, 'stickers');
    }

    public function test_deleting_removes_the_row_and_the_file(): void
    {
        $this->signIn();
        $sticker = Sticker::factory()->create();
        Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG);

        $this->delete("/dashboard/stickers/{$sticker->id}")->assertRedirect(route('dashboard.stickers.index'));

        $this->assertDatabaseCount('stickers', 0);
        Storage::disk('local')->assertMissing($sticker->file_path);
    }

    public function test_the_preview_shows_an_unpublished_sticker_that_the_public_route_hides(): void
    {
        $this->signIn();
        $sticker = Sticker::factory()->unpublished()->create(['slug' => 'draft']);
        Storage::disk('local')->put($sticker->file_path, StickerFactory::SVG);

        $this->get("/dashboard/stickers/{$sticker->id}/preview")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/api/v1/stickers/draft/file')->assertNotFound();
    }

    public function test_the_forms_render(): void
    {
        $this->signIn();
        $sticker = Sticker::factory()->create(['name' => 'Pink bow']);

        $this->get('/dashboard/stickers/create')->assertOk()->assertSee('Add sticker');
        $this->get("/dashboard/stickers/{$sticker->id}/edit")->assertOk()->assertSee('Pink bow');
    }
}
