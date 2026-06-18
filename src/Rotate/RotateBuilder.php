<?php
// src/Rotate/RotateBuilder.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\ResourceException;

final class RotateBuilder
{
    private RotateOptions $options;
    /** @var string[] */
    private array $backgrounds = [];
    private ?Rng $rng = null;

    public function __construct() { $this->options = new RotateOptions(); }

    public static function make(): self { return new self(); }

    public function setOptions(RotateOptions $o): self { $this->options = $o; return $this; }

    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): self { $this->backgrounds = $backgrounds; return $this; }

    public function setRng(Rng $rng): self { $this->rng = $rng; return $this; }

    public function build(): RotateCaptcha
    {
        if ($this->backgrounds === []) {
            throw new ResourceException('旋转验证码必须提供背景图');
        }
        return new RotateCaptcha($this->options, $this->backgrounds, $this->rng ?? new Rng());
    }
}
