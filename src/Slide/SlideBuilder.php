<?php
// src/Slide/SlideBuilder.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\ResourceException;

final class SlideBuilder
{
    private SlideOptions $options;
    /** @var GraphImage[] */
    private array $graphs = [];
    /** @var string[] */
    private array $backgrounds = [];
    private ?Rng $rng = null;
    private bool $regionMode = false;

    public function __construct()
    {
        $this->options = new SlideOptions();
    }

    public static function make(): self { return new self(); }

    public function setOptions(SlideOptions $o): self { $this->options = $o; return $this; }

    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): self { $this->backgrounds = $backgrounds; return $this; }

    /** @param GraphImage[] $graphs */
    public function setGraphs(array $graphs): self { $this->graphs = $graphs; return $this; }

    public function setRng(Rng $rng): self { $this->rng = $rng; return $this; }

    public function build(): SlideCaptcha
    {
        $this->validate();
        $opts = $this->regionMode ? $this->options->withRegionMode(true) : $this->options;
        return new SlideCaptcha($opts, $this->graphs, $this->backgrounds, $this->rng ?? new Rng());
    }

    public function buildRegion(): SlideCaptcha
    {
        $this->regionMode = true;
        return $this->build();
    }

    private function validate(): void
    {
        if ($this->backgrounds === []) {
            throw new ResourceException('滑动验证码必须提供背景图');
        }
        if ($this->graphs === []) {
            throw new ResourceException('滑动验证码必须提供拼图素材');
        }
    }
}
