<?php
// tests/Base/CanvasTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Exception\ResourceException;
use PHPUnit\Framework\TestCase;

class CanvasTest extends TestCase
{
    private $tmpBg;

    protected function tearDown(): void
    {
        if ($this->tmpBg && file_exists($this->tmpBg)) {
            unlink($this->tmpBg);
        }
        parent::tearDown();
    }

    public function test_create_with_size(): void
    {
        $c = new Canvas(300, 220);
        self::assertSame(300, $c->getWidth());
        self::assertSame(220, $c->getHeight());
        $c->destroy();
    }

    public function test_from_path_jpeg(): void
    {
        $this->tmpBg = __DIR__ . '/../fixtures/tmp_bg.jpg';
        $src = new Canvas(60, 40);
        imagefilledrectangle($src->getResource(), 0, 0, 60, 40, imagecolorallocate($src->getResource(), 255, 0, 0));
        imagejpeg($src->getResource(), $this->tmpBg);
        $src->destroy();

        $c = Canvas::fromPath($this->tmpBg);
        self::assertSame(60, $c->getWidth());
        $c->destroy();
    }

    public function test_from_missing_path_throws(): void
    {
        $this->expectException(ResourceException::class);
        Canvas::fromPath(__DIR__ . '/nonexistent.jpg');
    }

    public function test_fill_and_pixel(): void
    {
        $c = new Canvas(10, 10);
        $c->fill(Color::fromHex('#000000'));
        $c->setPixel(5, 5, $c->allocateColor(Color::fromHex('#ffffff')));
        self::assertSame([255, 255, 255], $c->getRgbAt(5, 5));
        $c->destroy();
    }
}
