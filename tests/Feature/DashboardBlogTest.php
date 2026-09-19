<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\BlogBuildLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBlogTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_blogs_index_renders_with_tabs_and_statistics(): void
    {
        Blog::factory()->published()->create(['title' => 'Live Article']);
        Blog::factory()->create(['title' => 'Draft Article']);
        Blog::factory()->trashed()->create(['title' => 'Deleted Article']);

        $response = $this->get(route('dashboard.blogs.index'));

        $response->assertOk();
        $response->assertSee('Live Article');
        $response->assertSee('Draft Article');
        $response->assertDontSee('Deleted Article');
        $response->assertSee('Deleted');
    }

    public function test_blogs_index_displays_only_deleted_when_filtered_by_deleted_status(): void
    {
        Blog::factory()->published()->create(['title' => 'Live Article']);
        Blog::factory()->create(['title' => 'Draft Article']);
        Blog::factory()->trashed()->create(['title' => 'Deleted Article']);

        $response = $this->get(route('dashboard.blogs.index', ['status' => 'deleted']));

        $response->assertOk();
        $response->assertSee('Deleted Article');
        $response->assertDontSee('Live Article');
        $response->assertDontSee('Draft Article');
    }

    public function test_soft_deleting_a_blog_keeps_record_in_database_and_redirects_to_deleted_tab(): void
    {
        $blog = Blog::factory()->published()->create(['title' => 'To Be Deleted']);

        $response = $this->delete(route('dashboard.blogs.destroy', $blog));

        $response->assertRedirect(route('dashboard.blogs.index', ['status' => 'deleted']));
        $this->assertSoftDeleted('blogs', ['id' => $blog->id]);
    }

    public function test_can_view_logs_of_a_deleted_blog(): void
    {
        $blog = Blog::factory()->trashed()->create(['title' => 'Deleted Post With Logs']);

        BlogBuildLog::create([
            'blog_id' => $blog->id,
            'user_id' => $this->user->id,
            'action' => 'deleted',
            'status' => 'failed',
            'error_message' => 'GITHUB_TOKEN is not configured in .env. Skipping repository_dispatch.',
        ]);

        $response = $this->get(route('dashboard.blogs.logs', $blog));

        $response->assertOk();
        $response->assertSee('Deleted Post With Logs');
        $response->assertSee('Deleted');
        $response->assertSee('GITHUB_TOKEN is not configured');
    }

    public function test_can_restore_a_deleted_blog(): void
    {
        $blog = Blog::factory()->trashed()->create(['title' => 'Restorable Post']);

        $response = $this->patch(route('dashboard.blogs.restore', $blog));

        $response->assertRedirect(route('dashboard.blogs.index'));
        $this->assertNotSoftDeleted('blogs', ['id' => $blog->id]);
    }

    public function test_can_permanently_delete_a_blog(): void
    {
        $blog = Blog::factory()->trashed()->create(['title' => 'Permanently Deleted Post']);

        $response = $this->delete(route('dashboard.blogs.force-delete', $blog));

        $response->assertRedirect(route('dashboard.blogs.index', ['status' => 'deleted']));
        $this->assertDatabaseMissing('blogs', ['id' => $blog->id]);
    }
}
