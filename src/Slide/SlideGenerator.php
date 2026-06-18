<?php
// src/Slide/SlideGenerator.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class SlideGenerator
{
    private SlideOptions $options;
    /** @var GraphImage[] */
    private array $graphs;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /**
     * @param GraphImage[] $graphs
     * @param string[] $backgrounds
     */
    public function __construct(SlideOptions $options, array $graphs, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->graphs = $graphs;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): SlideCaptchaData
    {
        $imgSize = $this->options->getImageSize();
        $graph = $this->rng->pick($this->graphs);

        $overlay = Canvas::fromPath($graph->getOverlayPath());
        $mask = Canvas::fromPath($graph->getMaskPath());
        $shadow = Canvas::fromPath($graph->getShadowPath());

        $tileW = $overlay->getWidth();
        $tileH = $overlay->getHeight();

        $targetSize = $this->rng->range($this->options->getRangeGraphSize());
        $scaledOverlay = new Canvas($targetSize, $targetSize);
        $scaledMask = new Canvas($targetSize, $targetSize);
        $scaledShadow = new Canvas($targetSize, $targetSize);
        $scaledOverlay->copyResampled($overlay, 0, 0, 0, 0, $targetSize, $targetSize, $tileW, $tileH);
        $scaledMask->copyResampled($mask, 0, 0, 0, 0, $targetSize, $targetSize, $tileW, $tileH);
        $scaledShadow->copyResampled($shadow, 0, 0, 0, 0, $targetSize, $targetSize, $tileW, $tileH);
        $overlay->destroy(); $mask->destroy(); $shadow->destroy();

        $bgPath = $this->rng->pick($this->backgrounds);
        $bg = Canvas::fromPath($bgPath);
        $master = new Canvas($imgSize->getWidth(), $imgSize->getHeight());
        $master->copyResampled($bg, 0, 0, 0, 0, $imgSize->getWidth(), $imgSize->getHeight(), $bg->getWidth(), $bg->getHeight());
        $bg->destroy();

        $tx = $this->rng->getInt($targetSize + 10, max($targetSize + 11, $imgSize->getWidth() - $targetSize - 10));
        if ($this->options->isRegionMode() || $this->options->isEnableGraphVerticalRandom()) {
            $ty = $this->rng->getInt(10, max(11, $imgSize->getHeight() - $targetSize - 10));
        } else {
            $ty = $imgSize->getHeight() - $targetSize - (int)($targetSize * 0.2);
        }
        $angle = $this->rng->getInt(0, 360);

        (new MaskProcessor())->cutHole($master, $scaledMask, $scaledShadow, $tx, $ty);

        $tile = new Canvas($targetSize, $targetSize);
        (new MaskProcessor())->applyAlpha($tile, $scaledOverlay, $scaledMask);

        $scaledOverlay->destroy(); $scaledMask->destroy(); $scaledShadow->destroy();

        $jpeg = new JpegImage($master->releaseResource());
        $png = new PngImage($tile->releaseResource());
        $block = new Block($tx, $ty, $targetSize, $targetSize, $angle);

        return new SlideCaptchaData($block, $jpeg, $png);
    }
}
