<?php
// src/Slide/SlideCaptcha.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Rng;

final class SlideCaptcha
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
        return (new SlideGenerator($this->options, $this->graphs, $this->backgrounds, $this->rng))->generate();
    }
}
