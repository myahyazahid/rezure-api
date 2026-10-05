<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decides what an uploaded donate-method icon really is — from its bytes,
 * never from its name or the browser-supplied MIME type.
 *
 * Icons follow the sticker rules (`StickerFile`: PNG, WebP, or an SVG without
 * scripts, event handlers or a DOCTYPE) and also take JPEG, which stickers
 * don't: a logo exported as a photo-style image is still a logo.
 */
final class IconFile
{
    private const JPEG_SIGNATURE = "\xFF\xD8\xFF";

    /**
     * @return string `svg`, `png`, `jpg` or `webp`
     *
     * @throws InvalidArgumentException with a message fit to show the maintainer
     */
    public static function detectFormat(string $contents): string
    {
        if (str_starts_with($contents, self::JPEG_SIGNATURE)) {
            return 'jpg';
        }

        try {
            return StickerFile::detectFormat($contents, 'icons');
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === StickerFile::NOT_AN_IMAGE) {
                throw new InvalidArgumentException("That isn't a PNG, JPEG, WebP or SVG image.", previous: $e);
            }

            throw $e;
        }
    }
}
