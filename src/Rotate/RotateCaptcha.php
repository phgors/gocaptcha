<?php
// src/Rotate/RotateCaptcha.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Rng;

final class RotateCaptcha
{
    private RotateOptions $options;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /** @param string[] $backgrounds */
    public function __construct(RotateOptions $options, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): RotateCaptchaData
    {
        return (new RotateGenerator($this->options, $this->backgrounds, $this->rng))->generate();
    }
}
