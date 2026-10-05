<?php

namespace Tests\Unit;

use App\Support\StickerFile;
use Database\Factories\StickerFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StickerFileTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";

    public function test_it_recognises_each_format_from_its_bytes(): void
    {
        $this->assertSame('svg', StickerFile::detectFormat(StickerFactory::SVG));
        $this->assertSame('png', StickerFile::detectFormat(self::PNG));
        $this->assertSame('webp', StickerFile::detectFormat('RIFF'."\x24\x00\x00\x00".'WEBPVP8 '));
    }

    public function test_an_svg_may_start_with_an_xml_declaration_a_byte_order_mark_or_whitespace(): void
    {
        $this->assertSame('svg', StickerFile::detectFormat('<?xml version="1.0" encoding="UTF-8"?>'.StickerFactory::SVG));
        $this->assertSame('svg', StickerFile::detectFormat("\xEF\xBB\xBF".StickerFactory::SVG));
        $this->assertSame('svg', StickerFile::detectFormat("\n  ".StickerFactory::SVG));
    }

    public function test_real_world_svg_features_are_not_mistaken_for_dangerous_ones(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">'
            .'<defs><linearGradient id="g" gradientUnits="objectBoundingBox"><stop offset="0" stop-color="#fff"/></linearGradient></defs>'
            .'<style>.a{fill:url(#g)}</style><path class="a" d="M0 0h10v10z"/></svg>';

        $this->assertSame('svg', StickerFile::detectFormat($svg));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function refusedFiles(): array
    {
        $wrap = fn (string $inner): string => '<svg xmlns="http://www.w3.org/2000/svg">'.$inner.'</svg>';

        return [
            'empty' => ['', 'empty'],
            'plain text' => ['hello', 'isn\'t a PNG'],
            'a GIF' => ["GIF89a\x01\x00", 'isn\'t a PNG'],
            'svg with a script' => [$wrap('<script>alert(1)</script>'), '<script'],
            'svg with an uppercase script' => [$wrap('<SCRIPT>alert(1)</SCRIPT>'), '<script'],
            'svg with an event handler' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', 'event handler'],
            'svg with a handler on a child' => [$wrap('<circle onclick = "x()"/>'), 'event handler'],
            'svg with foreignObject' => [$wrap('<foreignObject><div/></foreignObject>'), 'foreignobject'],
            'svg with a javascript link' => [$wrap('<a href="javascript:alert(1)"><circle/></a>'), 'javascript:'],
            'svg with a doctype' => ['<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"/>', '<!doctype'],
            'svg with an entity' => ['<svg xmlns="http://www.w3.org/2000/svg"><!ENTITY x "y"/></svg>', '<!entity'],
            'svg that is not well-formed' => ['<svg xmlns="http://www.w3.org/2000/svg"><circle></svg>', 'well-formed'],
            'xml whose root is not svg' => ['<?xml version="1.0"?><html><svg/></html>', 'well-formed'],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_it_refuses_what_a_sticker_cannot_be(string $contents, string $messageContains): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($messageContains, '/').'/i');

        StickerFile::detectFormat($contents);
    }
}
