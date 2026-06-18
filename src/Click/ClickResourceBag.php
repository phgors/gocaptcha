<?php
// src/Click/ClickResourceBag.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Assets\Font;

final class ClickResourceBag
{
    /** @var string[] */
    private array $chars = [];
    /** @var Font[] */
    private array $fonts = [];
    /** @var string[] 背景图绝对路径 */
    private array $backgrounds = [];
    /** @var array<string,string> shape 名 => 图片路径 */
    private array $shapes = [];

    /** @return string[] */
    public function getChars(): array { return $this->chars; }
    /** @param string[] $chars */
    public function setChars(array $chars): void { $this->chars = array_values($chars); }

    /** @return Font[] */
    public function getFonts(): array { return $this->fonts; }
    /** @param Font[] $fonts */
    public function setFonts(array $fonts): void { $this->fonts = array_values($fonts); }

    /** @return string[] */
    public function getBackgrounds(): array { return $this->backgrounds; }
    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): void { $this->backgrounds = array_values($backgrounds); }

    /** @return array<string,string> */
    public function getShapes(): array { return $this->shapes; }
    /** @param array<string,string> $shapes */
    public function setShapes(array $shapes): void { $this->shapes = $shapes; }
}
