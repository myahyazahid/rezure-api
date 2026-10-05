<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decides what an uploaded QRIS image really is — from its bytes, never from
 * its name or the browser-supplied MIME type.
 *
 * A QRIS is a picture to scan, so only raster formats are accepted. SVG is
 * left out on purpose (unlike stickers): there is no reason to take on its
 * script surface for an image that is never styled or scaled by the client.
 */
final class QrisFile
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private const JPEG_SIGNATURE = "\xFF\xD8\xFF";

    /**
     * @return string `png`, `jpg` or `webp`
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

        throw new InvalidArgumentException('That isn\'t a PNG, JPEG or WebP image.');
    }
}
