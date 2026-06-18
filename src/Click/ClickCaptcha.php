<?php
// src/Click/ClickCaptcha.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Base\Rng;

final class ClickCaptcha
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
        return (new ClickGenerator($this->options, $this->resources, $this->rng, $this->shapeMode))->generate();
    }
}
