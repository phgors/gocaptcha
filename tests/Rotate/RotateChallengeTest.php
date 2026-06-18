<?php
// tests/Rotate/RotateChallengeTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Base\RangeVal;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Rotate\RotateOptions;
use PHPUnit\Framework\TestCase;

class RotateChallengeTest extends TestCase
{
    private static string $fixturePath;

    public static function setUpBeforeClass(): void
    {
        $dir = __DIR__ . '/../fixtures';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        self::$fixturePath = $dir . '/rotate_bg.png';
        $size = 220;
        $im = imagecreatetruecolor($size, $size);
        $black = imagecolorallocate($im, 0, 0, 0);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 109, $size - 1, $black);
        imagefilledrectangle($im, 110, 0, $size - 1, $size - 1, $white);
        imagepng($im, self::$fixturePath);
        imagedestroy($im);
    }

    public function test_master_keeps_vertical_edge_while_thumb_is_rotated(): void
    {
        $options = (new RotateOptions())
            ->withRangeAngle(new RangeVal(90, 90));
        $captcha = RotateBuilder::make()
            ->setOptions($options)
            ->setBackgrounds([self::$fixturePath])
            ->setRng(new Rng(1))
            ->build();

        $data = $captcha->generate();

        $masterGd = imagecreatefromstring($data->getMasterImage()->toBytes());
        $thumbGd = imagecreatefromstring($data->getThumbImage()->toBytes());
        self::assertNotFalse($masterGd);
        self::assertNotFalse($thumbGd);

        // master 正立（不旋转）：保留垂直边缘（左暗右亮）
        $masterLeftAvg = $this->avgBrightness($masterGd, 20, 65, 10, 20);
        $masterRightAvg = $this->avgBrightness($masterGd, 120, 65, 10, 20);
        self::assertLessThan(60, $masterLeftAvg, '主图左侧应为暗（正立未旋转，保留垂直边缘）');
        self::assertGreaterThan(195, $masterRightAvg, '主图右侧应为亮（正立未旋转，保留垂直边缘）');

        // thumb 旋转 90°：垂直边变水平边（上下区域差异显著）
        $thumbTopAvg = $this->avgBrightness($thumbGd, 60, 20, 20, 10);
        $thumbBottomAvg = $this->avgBrightness($thumbGd, 60, 120, 20, 10);
        self::assertGreaterThan(
            130,
            abs($thumbTopAvg - $thumbBottomAvg),
            '缩略图上下区域应差异显著（旋转 90° 后垂直边变水平边）'
        );
        $vals = [$thumbTopAvg, $thumbBottomAvg];
        sort($vals);
        self::assertLessThan(60, $vals[0], '缩略图上下区域其一应为暗');
        self::assertGreaterThan(195, $vals[1], '缩略图上下区域其一应为亮');

        imagedestroy($masterGd);
        imagedestroy($thumbGd);
    }

    public function test_thumb_alpha_makes_thumb_more_transparent(): void
    {
        $build = function (float $thumbAlpha): string {
            $options = (new RotateOptions())
                ->withRangeAngle(new RangeVal(0, 0))
                ->withThumbAlpha($thumbAlpha);
            $captcha = RotateBuilder::make()
                ->setOptions($options)
                ->setBackgrounds([self::$fixturePath])
                ->setRng(new Rng(1))
                ->build();
            return $captcha->generate()->getThumbImage()->toBytes();
        };

        $opaque = imagecreatefromstring($build(1.0));
        $faint = imagecreatefromstring($build(0.3));
        self::assertNotFalse($opaque);
        self::assertNotFalse($faint);

        $opaqueOpacity = $this->avgOpacity($opaque, 60, 60, 30, 30);
        $faintOpacity = $this->avgOpacity($faint, 60, 60, 30, 30);

        self::assertGreaterThan($faintOpacity + 20, $opaqueOpacity, 'thumbAlpha=1.0 应比 0.3 更不透明');
        self::assertGreaterThan(110, $opaqueOpacity, 'thumbAlpha=1.0 圆内应近乎不透明');
        self::assertLessThan(80, $faintOpacity, 'thumbAlpha=0.3 圆内应明显更透明');

        imagedestroy($opaque);
        imagedestroy($faint);
    }

    /**
     * @param resource|\GdImage $im
     */
    private function avgOpacity($im, int $x0, int $y0, int $w, int $h): float
    {
        $sum = 0.0;
        $count = 0;
        for ($y = $y0; $y < $y0 + $h; $y++) {
            for ($x = $x0; $x < $x0 + $w; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                if ($rgb === false) {
                    continue;
                }
                $a = ($rgb >> 24) & 0x7F;
                $sum += (127 - $a);
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : 0.0;
    }

    /**
     * @param resource|\GdImage $im
     */
    private function avgBrightness($im, int $x0, int $y0, int $w, int $h): float
    {
        $sum = 0.0;
        $count = 0;
        for ($y = $y0; $y < $y0 + $h; $y++) {
            for ($x = $x0; $x < $x0 + $w; $x++) {
                $rgb = imagecolorat($im, $x, $y);
                if ($rgb === false) {
                    continue;
                }
                $a = ($rgb >> 24) & 0x7F;
                if ($a >= 127) {
                    continue;
                }
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $sum += ($r + $g + $b) / 3;
                $count++;
            }
        }
        return $count > 0 ? $sum / $count : 0.0;
    }
}
