<?php
// src/Click/ClickGenerator.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Assets\Font;
use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Distortion;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\GenerationException;
use Phgors\GoCaptcha\Exception\ResourceException;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class ClickGenerator
{
    private ClickOptions $options;
    private ClickResourceBag $resources;
    private Rng $rng;
    private bool $shapeMode;

    public function __construct(ClickOptions $options, ClickResourceBag $resources, Rng $rng, bool $shapeMode)
    {
        $this->options = $options;
        $this->resources = $resources;
        $this->rng = $rng;
        $this->shapeMode = $shapeMode;
    }

    public function generate(): ClickCaptchaData
    {
        $opts = $this->options;
        $imageSize = $opts->getImageSize();
        $thumbSize = $opts->getThumbSize();

        $total = $this->rng->range($opts->getRangeLen());
        $verifyLen = min($this->rng->range($opts->getRangeVerifyLen()), $total);

        $master = $this->createMasterCanvas($imageSize->getWidth(), $imageSize->getHeight());

        $pool = $this->shapeMode ? array_keys($this->resources->getShapes()) : $this->resources->getChars();
        $chosen = $this->pickUnique($pool, $total);

        $placed = $this->placeItems($master, $chosen, $imageSize);

        $verifyDots = [];
        for ($i = 0; $i < $verifyLen; $i++) {
            $placedItem = $placed[$i];
            $verifyDots[] = new Dot($i, $placedItem['x'], $placedItem['y'], $placedItem['size']);
        }

        $thumb = $this->createThumbCanvas($verifyDots, $chosen, $thumbSize);

        $jpeg = new JpegImage($master->releaseResource());
        $png = new PngImage($thumb->releaseResource());

        return new ClickCaptchaData($verifyDots, $jpeg, $png);
    }

    private function createMasterCanvas(int $w, int $h): Canvas
    {
        $bgs = $this->resources->getBackgrounds();
        $bgPath = $this->rng->pick($bgs);
        $bg = Canvas::fromPath($bgPath);
        $canvas = new Canvas($w, $h);
        $canvas->copyResampled($bg, 0, 0, 0, 0, $w, $h, $bg->getWidth(), $bg->getHeight());
        $bg->destroy();
        return $canvas;
    }

    /**
     * @param array $chosen 已选定的项
     * @return array<int, array{x:int,y:int,size:int,text:string}>
     */
    private function placeItems(Canvas $master, array $chosen, $imageSize): array
    {
        $opts = $this->options;
        $w = $imageSize->getWidth();
        $h = $imageSize->getHeight();
        $placed = [];
        $maxAttempts = 200;
        foreach ($chosen as $text) {
            $size = $this->rng->range($opts->getRangeSize());
            $angle = $this->rng->getInt(-30, 30);
            $colorHex = $this->rng->pick($opts->getRangeColors());
            $color = Color::fromHex($colorHex);

            $attempts = 0;
            do {
                $x = $this->rng->getInt($size + 4, max($size + 5, $w - $size - 4));
                $y = $this->rng->getInt($size + 4, max($size + 5, $h - 4));
                $ok = $this->isPositionFree($x, $y, $size, $placed);
                $attempts++;
            } while (!$ok && $attempts < $maxAttempts);

            if (!$ok) {
                throw new GenerationException('字符放置碰撞超限，请增大画布或减少内容数');
            }

            if ($opts->isDisplayShadow()) {
                $shadow = Color::fromHex($opts->getShadowColor());
                $this->renderItem($master, $text, $size, $angle, $x + $opts->getShadowOffsetX(), $y + $opts->getShadowOffsetY(), $shadow);
            }
            $this->renderItem($master, $text, $size, $angle, $x, $y, $color);
            $placed[] = ['x' => $x, 'y' => $y, 'size' => $size, 'text' => $text];
        }
        return $placed;
    }

    private function isPositionFree(int $x, int $y, int $size, array $placed): bool
    {
        $minDist = (int)($size * 1.4);
        foreach ($placed as $p) {
            $dx = $x - $p['x'];
            $dy = $y - $p['y'];
            if (sqrt($dx * $dx + $dy * $dy) < $minDist) {
                return false;
            }
        }
        return true;
    }

    private function renderItem(Canvas $master, string $text, int $size, int $angle, int $x, int $y, Color $color): void
    {
        if ($this->shapeMode) {
            $shapes = $this->resources->getShapes();
            $path = $shapes[$text] ?? null;
            if ($path === null) {
                throw new ResourceException('图形素材缺失：' . $text);
            }
            $img = Canvas::fromPath($path);
            $dest = new Canvas($size, $size);
            $dest->copyResampled($img, 0, 0, 0, 0, $size, $size, $img->getWidth(), $img->getHeight());
            $master->mergeAlpha($dest, $x - (int)($size / 2), $y - (int)($size / 2), $size, $size);
            $img->destroy();
            $dest->destroy();
        } else {
            $font = $this->rng->pick($this->resources->getFonts());
            $colorIdx = $master->allocateColor($color);
            $master->ttfText((float)$size, (float)$angle, $x, $y, $colorIdx, $font->getPath(), $text);
        }
    }

    /**
     * @param Dot[] $dots
     */
    private function createThumbCanvas(array $dots, array $chosen, $thumbSize): Canvas
    {
        $opts = $this->options;
        $w = $thumbSize->getWidth();
        $h = $thumbSize->getHeight();
        $canvas = new Canvas($w, $h);
        $canvas->fill(Color::fromHex('#ffffff'));

        $distortion = new Distortion($this->rng);
        $distortion->drawSlimLines($canvas, 3, Color::fromHex('#eeeeee'));
        $distortion->drawCircles($canvas, 20, Color::fromHex('#dddddd'), 3);

        $count = count($dots);
        $slotW = (int)($w / max(1, $count));
        foreach ($dots as $i => $dot) {
            $text = $chosen[$i];
            $colorIdx = $canvas->allocateColor(Color::fromHex('#333333'));
            $font = $this->rng->pick($this->resources->getFonts());
            $size = (int)($h * 0.7);
            $tx = (int)($slotW * $i + $slotW / 2);
            $ty = (int)($h * 0.75);
            $canvas->ttfText((float)$size, 0, $tx, $ty, $colorIdx, $font->getPath(), (string)$text);
        }
        return $canvas;
    }

    private function pickUnique(array $pool, int $n): array
    {
        $pool = array_values($pool);
        $keys = $this->rng->shuffle(range(0, count($pool) - 1));
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            $result[] = $pool[$keys[$i]];
        }
        return $result;
    }
}
