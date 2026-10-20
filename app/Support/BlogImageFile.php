<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decides what an uploaded blog image really is — from its bytes, never from
 * its name or the browser-supplied MIME type.
 *
 * Blog images (a post's cover, pictures inside its body) are photos and
 * screenshots, so only raster formats are accepted. GIF is in for short
 * screen recordings; SVG is out, like `QrisFile`, because the public website
 * hotlinks these and there's no reason to take on its script surface.
 */
final class BlogImageFile
{
    /**
     * 5 MB — a full-width screenshot fits, a camera original usually doesn't
     * and should be resized first.
     */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Where uploads live on the private `local` disk.
     */
    public const DIRECTORY = 'blog-images';

    /**
     * What `detectFormat` can return, mapped to the `Content-Type` it is
     * served with.
     *
     * @var array<string, string>
     */
    public const MIME_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const JPEG_SIGNATURE = "\xFF\xD8\xFF";

    /**
     * @return string `png`, `jpg`, `webp` or `gif`
     *
     * @throws InvalidArgumentException with a message fit to show the maintainer
     */
    public static function detectFormat(string $contents): string
    {
        if ($contents === '') {
            throw new InvalidArgumentException('The file is empty.');
        }

        if (str_starts_with($contents, self::PNG_SIGNATURE)) {
            return 'png';
        }

        if (str_starts_with($contents, self::JPEG_SIGNATURE)) {
            return 'jpg';
        }

        if (str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP') {
            return 'webp';
        }

        if (str_starts_with($contents, 'GIF87a') || str_starts_with($contents, 'GIF89a')) {
            return 'gif';
        }

        throw new InvalidArgumentException('That isn\'t a PNG, JPEG, WebP or GIF image.');
    }

    /**
     * The name a file is stored and served under: its SHA-256 plus format.
     * Content-addressed, so uploading the same image twice gives the same
     * URL and one file on disk, and a URL never starts pointing at different
     * bytes — which is what lets the file route cache it forever.
     */
    public static function filenameFor(string $contents): string
    {
        return hash('sha256', $contents).'.'.self::detectFormat($contents);
    }

    public static function pathFor(string $filename): string
    {
        return self::DIRECTORY.'/'.$filename;
    }
}
