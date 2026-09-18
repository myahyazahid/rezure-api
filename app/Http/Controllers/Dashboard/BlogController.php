<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\BlogRequest;
use App\Models\Blog;
use App\Services\GitHubWebhookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $query = Blog::query();

        if ($request->filled('status') && in_array($request->query('status'), ['published', 'draft'], true)) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = trim($request->query('q'));
            $query->where(function ($b) use ($q): void {
                $b->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%");
            });
        }

        $blogs = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $stats = [
            'total' => Blog::count(),
            'published' => Blog::where('status', 'published')->count(),
            'draft' => Blog::where('status', 'draft')->count(),
        ];

        return view('dashboard.blogs.index', [
            'blogs' => $blogs,
            'stats' => $stats,
            'currentStatus' => $request->query('status', 'all'),
            'searchQuery' => $request->query('q', ''),
        ]);
    }

    public function create(): View
    {
        return view('dashboard.blogs.form', [
            'blog' => new Blog(),
            'isEditing' => false,
        ]);
    }

    public function store(BlogRequest $request, GitHubWebhookService $webhookService): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->ensureUniqueSlug($data['slug'] ?? '', $data['title']);
        $data['tags'] = $this->parseTags($data['tags'] ?? null);
        $data['user_id'] = auth()->id();

        if ($data['status'] === 'published') {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $blog = Blog::create($data);

        if ($blog->status === 'published') {
            $webhookService->dispatchBlogUpdated();
        }

        return redirect()->route('dashboard.blogs.index')->with('status', 'Blog post created successfully.');
    }

    public function edit(Blog $blog): View
    {
        return view('dashboard.blogs.form', [
            'blog' => $blog,
            'isEditing' => true,
        ]);
    }

    public function update(BlogRequest $request, Blog $blog, GitHubWebhookService $webhookService): RedirectResponse
    {
        $wasPublished = $blog->status === 'published';

        $data = $request->validated();
        $data['slug'] = $this->ensureUniqueSlug($data['slug'] ?? '', $data['title'], $blog->id);
        $data['tags'] = $this->parseTags($data['tags'] ?? null);

        if ($data['status'] === 'published' && ! $blog->published_at) {
            $data['published_at'] = $data['published_at'] ?? now();
        }

        $blog->update($data);

        if ($wasPublished || $blog->status === 'published') {
            $webhookService->dispatchBlogUpdated();
        }

        return redirect()->route('dashboard.blogs.index')->with('status', 'Blog post updated successfully.');
    }

    public function destroy(Blog $blog, GitHubWebhookService $webhookService): RedirectResponse
    {
        $wasPublished = $blog->status === 'published';
        $blog->delete();

        if ($wasPublished) {
            $webhookService->dispatchBlogUpdated();
        }

        return redirect()->route('dashboard.blogs.index')->with('status', 'Blog post deleted.');
    }

    public function togglePublish(Blog $blog, GitHubWebhookService $webhookService): RedirectResponse
    {
        if ($blog->status === 'published') {
            $blog->status = 'draft';
        } else {
            $blog->status = 'published';
            $blog->published_at = $blog->published_at ?? now();
        }

        $blog->save();
        $webhookService->dispatchBlogUpdated();

        $msg = $blog->status === 'published' ? 'Post published.' : 'Post moved to draft.';

        return redirect()->back()->with('status', $msg);
    }

    private function ensureUniqueSlug(string $slug, string $title, ?int $ignoreId = null): string
    {
        $base = trim($slug) !== '' ? Str::slug($slug) : Str::slug($title);
        if ($base === '') {
            $base = 'post';
        }

        $final = $base;
        $counter = 1;

        while (Blog::where('slug', $final)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $final = "{$base}-{$counter}";
            $counter++;
        }

        return $final;
    }

    private function parseTags(mixed $tags): array
    {
        if (is_array($tags)) {
            return array_values(array_filter(array_map('trim', $tags)));
        }

        if (is_string($tags)) {
            return array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        return [];
    }
}
