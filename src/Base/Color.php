<?php
namespace Phgors\GoCaptcha\Base;

use Phgors\GoCaptcha\Exception\ResourceException;

final class Color
{
    private int $r;
    private int $g;
    private int $b;
    private int $alpha;

    public function __construct(int $r, int $g, int $b, int $alpha = 0)
    {
        if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255) {
            throw new \InvalidArgumentException('RGB 分量必须在 0-255 之间');
        }
        if ($alpha < 0 || $alpha > 127) {
            throw new \InvalidArgumentException('alpha 必须在 0-127 之间');
        }
        $this->r = $r;
        $this->g = $g;
        $this->b = $b;
        $this->alpha = $alpha;
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');
        if (!ctype_xdigit($hex) || (strlen($hex) !== 3 && strlen($hex) !== 6)) {
            throw new \InvalidArgumentException('非法十六进制颜色：' . $hex);
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return new self(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    public function getR(): int { return $this->r; }
    public function getG(): int { return $this->g; }
    public function getB(): int { return $this->b; }
    public function getAlpha(): int { return $this->alpha; }

    public function allocate($image): int
    {
        $result = imagecolorallocatealpha($image, $this->r, $this->g, $this->b, $this->alpha);
        if ($result === false) {
            throw new ResourceException('颜色分配失败');
        }
        return $result;
    }
}
