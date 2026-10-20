<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\BlogImageFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class BlogImageController extends Controller
{
    /**
     * A blog image — featured image or one inside a post's body — streamed
     * from the private disk, the way sticker files are. The public website
     * hotlinks these from `featured_image` and the post's HTML.
     *
     * The filename is the content's SHA-256, so the bytes behind a URL never
     * change: that hash is the `ETag` and the file may be cached forever.
     * The route only matches `{sha256}.{png|jpg|webp|gif}`.
     */
    public function show(Request $request, string $filename): Response
    {
        $disk = Storage::disk('local');
        $path = BlogImageFile::pathFor($filename);
        abort_unless($disk->exists($path), 404);

        $format = pathinfo($filename, PATHINFO_EXTENSION);

        $response = $disk->response($path, null, [
            'Content-Type' => BlogImageFile::MIME_TYPES[$format],
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
        $response->setEtag(pathinfo($filename, PATHINFO_FILENAME));
        $response->isNotModified($request);

        return $response;
    }
}
