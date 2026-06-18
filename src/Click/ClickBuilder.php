<?php
// src/Click/ClickBuilder.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Assets\Font;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\GenerationException;
use Phgors\GoCaptcha\Exception\ResourceException;

final class ClickBuilder
{
    private ClickOptions $options;
    private ClickResourceBag $resources;
    private ?Rng $rng = null;
    private bool $shapeMode = false;

    public function __construct()
    {
        $this->options = new ClickOptions();
        $this->resources = new ClickResourceBag();
    }

    public static function make(): self
    {
        return new self();
    }

    public function setOptions(ClickOptions $options): self
    {
        $this->options = $options;
        return $this;
    }

    /** @param string[] $chars */
    public function setChars(array $chars): self
    {
        $this->resources->setChars($chars);
        return $this;
    }

    /** @param Font[] $fonts */
    public function setFonts(array $fonts): self
    {
        $this->resources->setFonts($fonts);
        return $this;
    }

    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): self
    {
        $this->resources->setBackgrounds($backgrounds);
        return $this;
    }

    /** @param array<string,string> $shapes */
    public function setShapes(array $shapes): self
    {
        $this->resources->setShapes($shapes);
        return $this;
    }

    public function setRng(Rng $rng): self
    {
        $this->rng = $rng;
        return $this;
    }

    public function build(): ClickCaptcha
    {
        $this->validate();
        return new ClickCaptcha(
            $this->options,
            $this->resources,
            $this->rng ?? new Rng(),
            $this->shapeMode
        );
    }

    public function buildShape(): ClickCaptcha
    {
        $this->shapeMode = true;
        return $this->build();
    }

    private function validate(): void
    {
        if ($this->resources->getBackgrounds() === []) {
            throw new ResourceException('点选验证码必须提供背景图');
        }
        if (!$this->shapeMode && $this->resources->getFonts() === []) {
            throw new ResourceException('文本点选必须提供字体');
        }
        $need = $this->options->getRangeLen()->getMax();
        if ($this->shapeMode) {
            if (count($this->resources->getShapes()) < $need) {
                throw new GenerationException('图形素材数量必须大于 rangeLen.Max=' . $need);
            }
        } else {
            if (count($this->resources->getChars()) < $need) {
                throw new GenerationException('字符集数量必须大于 rangeLen.Max=' . $need);
            }
        }
    }
}
