<?php

namespace App\Support;

use DOMDocument;
use InvalidArgumentException;

/**
 * Decides what an uploaded sticker file really is — from its bytes, never
 * from its name or the browser-supplied MIME type — and refuses anything the
 * desktop client shouldn't be handed.
 *
 * The client shows stickers in an `<img>`, where an SVG can't run scripts,
 * and re-checks what it downloads. This is the first of those two gates, and
 * the one a maintainer sees: a rejected upload says why.
 */
final class StickerFile
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * Markup an SVG sticker has no business containing. Matched
     * case-insensitively against the whole file, so it also catches the
     * attribute forms (`onload=`, `href="javascript:…"`) a parser would
     * need to walk the tree to find.
     *
     * @var list<string>
     */
    private const FORBIDDEN_SVG = [
        '<script',
        '<foreignobject',
        '<iframe',
        '<embed',
        '<object',
        '<!doctype',
        '<!entity',
        'javascript:',
    ];

    /**
     * @return string `svg`, `png` or `webp`
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

        if (str_starts_with($contents, 'RIFF') && substr($contents, 8, 4) === 'WEBP') {
            return 'webp';
        }

        if (self::looksLikeSvg($contents)) {
            self::assertSafeSvg($contents);

            return 'svg';
        }

        throw new InvalidArgumentException('That isn\'t a PNG, WebP or SVG image.');
    }

    /**
     * A `<!DOCTYPE` or comment may come first (editors export both), so
     * those count as "this is an SVG" here and are then refused — or not —
     * by `assertSafeSvg`, with a message that says why, instead of being
     * reported as not an image at all.
     */
    private static function looksLikeSvg(string $contents): bool
    {
        $head = strtolower(ltrim(self::withoutByteOrderMark($contents)));

        $opensLikeMarkup = str_starts_with($head, '<svg')
            || str_starts_with($head, '<?xml')
            || str_starts_with($head, '<!doctype')
            || str_starts_with($head, '<!--');

        return $opensLikeMarkup && str_contains(strtolower($contents), '<svg');
    }

    private static function withoutByteOrderMark(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }

    private static function assertSafeSvg(string $contents): void
    {
        $lower = strtolower($contents);

        foreach (self::FORBIDDEN_SVG as $needle) {
            if (str_contains($lower, $needle)) {
                throw new InvalidArgumentException("The SVG contains \"{$needle}\", which stickers can't use.");
            }
        }

        if (preg_match('/\son[a-z]+\s*=/i', $contents) === 1) {
            throw new InvalidArgumentException('The SVG has an event handler attribute (onload, onclick…), which stickers can\'t use.');
        }

        // LIBXML_NONET: never fetch anything while parsing. Entities are off
        // by default (no LIBXML_NOENT) and DOCTYPEs were refused above.
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $parsed = $document->loadXML($contents, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $parsed || $document->documentElement === null || strtolower($document->documentElement->localName) !== 'svg') {
            throw new InvalidArgumentException('The SVG isn\'t well-formed.');
        }
    }
}
