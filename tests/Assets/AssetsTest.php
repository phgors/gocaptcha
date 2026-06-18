<?php
namespace Phgors\GoCaptcha\Tests\Assets;

use Phgors\GoCaptcha\Assets\Font;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase
{
    public function test_font_holds_path_and_size(): void
    {
        $f = new Font('/path/font.ttf', 24);
        self::assertSame('/path/font.ttf', $f->getPath());
        self::assertSame(24, $f->getSize());
    }

    public function test_default_chars_returns_arrays(): void
    {
        $chars = DefaultAssets::chineseChars();
        self::assertIsArray($chars);
        self::assertNotEmpty($chars);
        self::assertNotEmpty(DefaultAssets::alnumChars());
    }
}
