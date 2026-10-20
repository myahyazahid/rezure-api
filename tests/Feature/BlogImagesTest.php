<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlogImagesTest extends TestCase
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

    private function image(string $contents = self::PNG, string $name = 'cover.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_guests_cannot_upload_images(): void
    {
        $this->post(route('dashboard.blogs.images.store'), ['image' => $this->image()])
            ->assertRedirect(route('login'));

        $this->assertSame([], Storage::disk('local')->files('blog-images'));
    }

    public function test_an_upload_is_stored_under_its_hash_and_answers_with_its_public_url(): void
    {
        $this->signIn();
        $filename = hash('sha256', self::PNG).'.png';

        $this->postJson(route('dashboard.blogs.images.store'), ['image' => $this->image()])
            ->assertCreated()
            ->assertExactJson(['url' => route('api.v1.blog-images.show', $filename)]);

        Storage::disk('local')->assertExists("blog-images/{$filename}");
    }

    public function test_uploading_the_same_image_twice_gives_the_same_url(): void
    {
        $this->signIn();

        $first = $this->postJson(route('dashboard.blogs.images.store'), ['image' => $this->image(name: 'a.png')])->json('url');
        $second = $this->postJson(route('dashboard.blogs.images.store'), ['image' => $this->image(name: 'b.png')])->json('url');

        $this->assertSame($first, $second);
        $this->assertCount(1, Storage::disk('local')->files('blog-images'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function acceptedImages(): array
    {
        return [
            'jpeg' => ["\xFF\xD8\xFF\xE0\x00\x10JFIF", 'jpg'],
            'webp' => ['RIFF'."\x24\x00\x00\x00".'WEBPVP8 ', 'webp'],
            'gif' => ['GIF89a'."\x01\x00\x01\x00", 'gif'],
        ];
    }

    #[DataProvider('acceptedImages')]
    public function test_the_format_comes_from_the_bytes_not_the_name(string $contents, string $format): void
    {
        $this->signIn();

        $this->postJson(route('dashboard.blogs.images.store'), ['image' => $this->image($contents, 'misnamed.png')])
            ->assertCreated()
            ->assertJsonPath('url', route('api.v1.blog-images.show', hash('sha256', $contents).".{$format}"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedFiles(): array
    {
        return [
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            'html' => ['<html><script>alert(1)</script></html>'],
            'empty' => [''],
        ];
    }

    #[DataProvider('rejectedFiles')]
    public function test_anything_that_is_not_a_raster_image_is_refused(string $contents): void
    {
        $this->signIn();

        $this->postJson(route('dashboard.blogs.images.store'), ['image' => $this->image($contents)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->assertSame([], Storage::disk('local')->files('blog-images'));
    }

    public function test_images_over_the_size_limit_are_refused(): void
    {
        $this->signIn();

        $this->postJson(route('dashboard.blogs.images.store'), [
            'image' => UploadedFile::fake()->create('huge.png', 5 * 1024 + 1),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_the_public_route_serves_the_image_with_cache_headers(): void
    {
        $filename = hash('sha256', self::PNG).'.png';
        Storage::disk('local')->put("blog-images/{$filename}", self::PNG);

        $response = $this->get(route('api.v1.blog-images.show', $filename));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('ETag', '"'.hash('sha256', self::PNG).'"');
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
        $this->assertSame(self::PNG, $response->streamedContent());

        $this->get(route('api.v1.blog-images.show', $filename), ['If-None-Match' => '"'.hash('sha256', self::PNG).'"'])
            ->assertStatus(304);
    }

    public function test_the_public_route_is_a_404_for_unknown_or_malformed_names(): void
    {
        $this->get('/api/v1/blog-images/'.str_repeat('a', 64).'.png')->assertNotFound();
        $this->get('/api/v1/blog-images/../../.env')->assertNotFound();
        $this->get('/api/v1/blog-images/cover.png')->assertNotFound();
        $this->get('/api/v1/blog-images/'.str_repeat('a', 64).'.svg')->assertNotFound();
    }

    public function test_the_blog_form_offers_image_uploads(): void
    {
        $this->signIn();

        $this->get(route('dashboard.blogs.create'))
            ->assertOk()
            ->assertSee('editor-image-file', false)
            ->assertSee('btn-upload-featured-image', false);
    }
}
