<?php
// src/Slide/MaskProcessor.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Canvas;

final class MaskProcessor
{
    /**
     * 将 overlay 按 mask 灰度合成为带透明通道的拼图块写入 dest。
     * mask 越白 -> 越不透明；mask 越黑 -> 越透明。
     */
    public function applyAlpha(Canvas $dest, Canvas $overlay, Canvas $mask): void
    {
        $w = min($dest->getWidth(), $overlay->getWidth(), $mask->getWidth());
        $h = min($dest->getHeight(), $overlay->getHeight(), $mask->getHeight());
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($overlay->getResource(), $x, $y);
                if ($rgb === false) {
                    continue;
                }
                $maskRgb = imagecolorat($mask->getResource(), $x, $y);
                if ($maskRgb === false) {
                    continue;
                }
                $maskGray = (int)(($maskRgb >> 16) & 0xFF);
                $alpha = (int)(127 - ($maskGray / 255) * 127);
                $r = (int)(($rgb >> 16) & 0xFF);
                $g = (int)(($rgb >> 8) & 0xFF);
                $b = (int)($rgb & 0xFF);
                $color = imagecolorallocatealpha($dest->getResource(), $r, $g, $b, $alpha);
                if ($color !== false) {
                    imagesetpixel($dest->getResource(), $x, $y, $color);
                }
            }
        }
    }

    /**
     * 在主图 (x,y) 处挖缺口：按 mask 灰度阈值 + shadow 半透明阴影实现凹陷效果。
     */
    public function cutHole(Canvas $master, Canvas $mask, Canvas $shadow, int $x, int $y): void
    {
        $w = min($mask->getWidth(), $shadow->getWidth());
        $h = min($mask->getHeight(), $shadow->getHeight());
        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $mx = $x + $i;
                $my = $y + $j;
                if ($mx < 0 || $my < 0 || $mx >= $master->getWidth() || $my >= $master->getHeight()) {
                    continue;
                }
                $maskRgb = imagecolorat($mask->getResource(), $i, $j);
                if ($maskRgb === false) {
                    continue;
                }
                $maskGray = (int)(($maskRgb >> 16) & 0xFF);
                if ($maskGray < 30) {
                    continue;
                }

                // 阴影：按 shadow 灰度叠暗色
                $shadowRgb = imagecolorat($shadow->getResource(), $i, $j);
                if ($shadowRgb !== false) {
                    $sGray = (int)(($shadowRgb >> 16) & 0xFF);
                    $sAlpha = (int)(127 - ($sGray / 255) * 90);
                    $sCol = imagecolorallocatealpha($master->getResource(), 0, 0, 0, $sAlpha);
                    if ($sCol !== false) {
                        imagesetpixel($master->getResource(), $mx, $my, $sCol);
                    }
                }
            }
        }
    }
}
