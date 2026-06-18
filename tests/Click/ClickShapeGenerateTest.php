<?php
// tests/Click/ClickShapeGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickBuilder;
use PHPUnit\Framework\TestCase;

class ClickShapeGenerateTest extends TestCase
{
    private static array $shapePaths = [];
    private static string $fixtureDir;

    public static function setUpBeforeClass(): void
    {
        self::$fixtureDir = __DIR__ . '/../fixtures';
        if (!is_dir(self::$fixtureDir)) {
            mkdir(self::$fixtureDir, 0777, true);
        }
        $colors = [
            [255, 0, 0],
            [0, 200, 0],
            [0, 0, 255],
            [255, 180, 0],
            [180, 0, 255],
            [0, 200, 200],
        ];
        foreach ($colors as $i => $c) {
            $path = self::$fixtureDir . '/shape_' . $i . '.png';
            $im = imagecreatetruecolor(40, 40);
            $col = imagecolorallocate($im, $c[0], $c[1], $c[2]);
            imagefilledrectangle($im, 0, 0, 39, 39, $col);
            imagepng($im, $path);
            imagedestroy($im);
            self::$shapePaths[$i] = $path;
        }
    }

    public function test_shape_mode_generates_without_fonts(): void
    {
        if (DefaultAssets::backgrounds() === []) {
            self::markTestSkipped('未提供背景素材');
        }

        $shapes = [
            'shape0' => self::$shapePaths[0],
            'shape1' => self::$shapePaths[1],
            'shape2' => self::$shapePaths[2],
            'shape3' => self::$shapePaths[3],
            'shape4' => self::$shapePaths[4],
            'shape5' => self::$shapePaths[5],
        ];

        $captcha = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setShapes($shapes)
            ->buildShape();

        $data = $captcha->generate();

        self::assertNotEmpty($data->getDots());
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getThumbImage()->toBase64());

        $thumbGd = imagecreatefromstring($data->getThumbImage()->toBytes());
        self::assertNotFalse($thumbGd, '缩略图应为合法 PNG');
        imagedestroy($thumbGd);
    }
}
