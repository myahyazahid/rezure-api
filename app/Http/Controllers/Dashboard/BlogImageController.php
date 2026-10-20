<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\BlogImageRequest;
use App\Support\BlogImageFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BlogImageController extends Controller
{
    /**
     * Stores an image for a blog post — its featured image or one inside the
     * body — and answers with the absolute URL the public website loads it
     * from. Called by the editor with `fetch`, so the answer is JSON (a
     * validation failure is the usual `422` JSON).
     *
     * Not tied to a post: the editor uploads while a new post has no id yet,
     * and the URL simply ends up in `featured_image` or `content`.
     */
    public function store(BlogImageRequest $request): JsonResponse
    {
        $contents = (string) $request->file('image')->get();
        $filename = BlogImageFile::filenameFor($contents);

        Storage::disk('local')->put(BlogImageFile::pathFor($filename), $contents);

        return response()->json([
            'url' => route('api.v1.blog-images.show', $filename),
        ], 201);
    }
}
