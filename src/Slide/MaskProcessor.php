<?php
// src/Slide/MaskProcessor.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Canvas;

final class MaskProcessor
{
    /**
     * 生成拼图块：dest = 背景裁剪(bgCrop) 在 mask 形状内显示，并叠加 overlay（边框/高亮）。
     * 官方素材的形状信息在 alpha 通道（alpha=0 形状内、alpha=127 形状外）。
     */
    public function applyAlpha(Canvas $dest, Canvas $bgCrop, Canvas $overlay, Canvas $mask): void
    {
        $w = min($dest->getWidth(), $bgCrop->getWidth(), $overlay->getWidth(), $mask->getWidth());
        $h = min($dest->getHeight(), $bgCrop->getHeight(), $overlay->getHeight(), $mask->getHeight());
        $res = $dest->getResource();
        imagealphablending($res, false);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $maskRgb = imagecolorat($mask->getResource(), $x, $y);
                if ($maskRgb === false) {
                    continue;
                }
                $maskA = ($maskRgb >> 24) & 0x7F;
                if ($maskA >= 127) {
                    continue; // 形状外：保持透明
                }
                // 形状内：取背景内容
                $bgRgb = imagecolorat($bgCrop->getResource(), $x, $y);
                $r = (int)(($bgRgb >> 16) & 0xFF);
                $g = (int)(($bgRgb >> 8) & 0xFF);
                $b = (int)($bgRgb & 0xFF);
                // 叠 overlay（按 overlay alpha Over 合成，形成边框/高亮）
                $ovRgb = imagecolorat($overlay->getResource(), $x, $y);
                if ($ovRgb !== false) {
                    $ovA = ($ovRgb >> 24) & 0x7F;
                    if ($ovA < 127) {
                        $ratio = (127 - $ovA) / 127;
                        $r = (int)($r * (1 - $ratio) + (($ovRgb >> 16) & 0xFF) * $ratio);
                        $g = (int)($g * (1 - $ratio) + (($ovRgb >> 8) & 0xFF) * $ratio);
                        $b = (int)($b * (1 - $ratio) + ($ovRgb & 0xFF) * $ratio);
                    }
                }
                $col = imagecolorallocatealpha($res, $r, $g, $b, 0);
                if ($col !== false) {
                    imagesetpixel($res, $x, $y, $col);
                }
            }
        }
        imagealphablending($res, true);
    }

    /**
     * 在主图 (x,y) 处按 mask 形状叠 shadow 阴影，形成凹陷缺口。
     * mask alpha<127 为形状内；shadow alpha 越小阴影越深。
     */
    public function cutHole(Canvas $master, Canvas $mask, Canvas $shadow, int $x, int $y): void
    {
        $w = min($mask->getWidth(), $shadow->getWidth());
        $h = min($mask->getHeight(), $shadow->getHeight());
        $res = $master->getResource();
        imagealphablending($res, true); // master 为真彩色，开启混合使半透明阴影与背景叠加变暗
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
                $maskA = ($maskRgb >> 24) & 0x7F;
                if ($maskA >= 127) {
                    continue; // 形状外：不挖
                }
                $shadowRgb = imagecolorat($shadow->getResource(), $i, $j);
                if ($shadowRgb === false) {
                    continue;
                }
                $shadowA = ($shadowRgb >> 24) & 0x7F;
                if ($shadowA >= 127) {
                    continue; // shadow 透明：无阴影
                }
                $opacity = (127 - $shadowA) / 127;       // shadow 不透明度
                $sAlpha = (int)(127 - $opacity * 110);    // 映射为阴影 alpha，增强缺口可见性
                $sCol = imagecolorallocatealpha($res, 0, 0, 0, $sAlpha);
                if ($sCol !== false) {
                    imagesetpixel($res, $mx, $my, $sCol);
                }
            }
        }
    }
}
