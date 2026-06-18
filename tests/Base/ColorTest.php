<?php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_from_hex_6_digits(): void
    {
        $c = Color::fromHex('#FF8800');
        self::assertSame(255, $c->getR());
        self::assertSame(136, $c->getG());
        self::assertSame(0, $c->getB());
    }

    public function test_from_hex_without_hash(): void
    {
        $c = Color::fromHex('00ff00');
        self::assertSame(0, $c->getR());
        self::assertSame(255, $c->getG());
        self::assertSame(0, $c->getB());
    }

    public function test_from_hex_3_digits_expanded(): void
    {
        $c = Color::fromHex('#f80');
        self::assertSame(255, $c->getR());
        self::assertSame(136, $c->getG());
        self::assertSame(0, $c->getB());
    }

    public function test_default_alpha_is_zero(): void
    {
        $c = Color::fromHex('#000000');
        self::assertSame(0, $c->getAlpha());
    }

    public function test_invalid_hex_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::fromHex('xyz');
    }

    public function test_rgb_out_of_range_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Color(300, 0, 0);
    }

    public function test_allocate_returns_int(): void
    {
        $im = imagecreatetruecolor(10, 10);
        self::assertIsInt(Color::fromHex('#ff8800')->allocate($im));
        imagedestroy($im);
    }

    public function test_allocate_throws_when_color_table_full(): void
    {
        $im = imagecreate(10, 10);
        for ($i = 0; $i < 256; $i++) {
            imagecolorallocate($im, $i % 256, ($i * 2) % 256, ($i * 3) % 256);
        }
        $this->expectException(\Phgors\GoCaptcha\Exception\ResourceException::class);
        try {
            Color::fromHex('#ff8800')->allocate($im);
        } finally {
            imagedestroy($im);
        }
    }
}
