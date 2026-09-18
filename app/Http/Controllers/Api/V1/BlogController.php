<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    /**
     * Public endpoint returning published blog posts.
     * Formatted with a top-level "data" array for the VitePress static builder.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);

        $blogs = Blog::query()
            ->published()
            ->with('user')
            ->orderByDesc('published_at')
            ->paginate($perPage);

        $formatted = $blogs->through(fn (Blog $blog) => $blog->toApiArray());

        return response()->json($formatted);
    }

    /**
     * Public endpoint to fetch a single published blog post by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $blog = Blog::query()
            ->published()
            ->with('user')
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'data' => $blog->toApiArray(),
        ]);
    }
}
