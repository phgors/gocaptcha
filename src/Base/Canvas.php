<?php
// src/Base/Canvas.php
namespace Phgors\GoCaptcha\Base;

use Phgors\GoCaptcha\Exception\ResourceException;

final class Canvas
{
    /** @var resource|\GdImage */
    private $resource;
    private int $width;
    private int $height;

    public function __construct(int $width, int $height)
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('画布尺寸必须为正数');
        }
        $res = @imagecreatetruecolor($width, $height);
        if ($res === false) {
            throw new ResourceException('画布创建失败');
        }
        $this->resource = $res;
        $this->width = $width;
        $this->height = $height;
        imagealphablending($res, true);
        imagesavealpha($res, true);
        $transparent = imagecolorallocatealpha($res, 0, 0, 0, 127);
        imagefill($res, 0, 0, $transparent);
    }

    public static function fromPath(string $path): self
    {
        if (!file_exists($path)) {
            throw new ResourceException('图片不存在：' . $path);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $res = @imagecreatefromjpeg($path);
        } elseif ($ext === 'png') {
            $res = @imagecreatefrompng($path);
        } elseif ($ext === 'gif') {
            $res = @imagecreatefromgif($path);
        } else {
            throw new ResourceException('不支持的图片格式：' . $ext);
        }
        if ($res === false) {
            throw new ResourceException('图片解码失败：' . $path);
        }
        imagealphablending($res, true);
        imagesavealpha($res, true);
        $c = new self(imagesx($res), imagesy($res));
        imagecopy($c->resource, $res, 0, 0, 0, 0, imagesx($res), imagesy($res));
        imagedestroy($res);
        return $c;
    }

    /** @return resource|\GdImage */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * 取出底层 GD 资源并放弃所有权（析构时不再销毁）。
     * 用于把资源移交给 JpegImage/PngImage 等值对象，避免双重释放。
     * @return resource|\GdImage|null
     */
    public function releaseResource()
    {
        $res = $this->resource;
        $this->resource = null;
        return $res;
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }

    public function allocateColor(Color $color): int
    {
        return imagecolorallocatealpha($this->resource, $color->getR(), $color->getG(), $color->getB(), $color->getAlpha());
    }

    public function fill(Color $color): void
    {
        imagefill($this->resource, 0, 0, $this->allocateColor($color));
    }

    public function setPixel(int $x, int $y, int $color): void
    {
        imagesetpixel($this->resource, $x, $y, $color);
    }

    /**
     * @return array{0:int,1:int,2:int}|null [r,g,b]
     */
    public function getRgbAt(int $x, int $y): ?array
    {
        $rgb = imagecolorat($this->resource, $x, $y);
        if ($rgb === false) {
            return null;
        }
        return [(int)(($rgb >> 16) & 0xFF), (int)(($rgb >> 8) & 0xFF), (int)($rgb & 0xFF)];
    }

    public function getAlphaAt(int $x, int $y): int
    {
        $rgb = imagecolorat($this->resource, $x, $y);
        if ($rgb === false) {
            return 127;
        }
        return (int)(($rgb >> 24) & 0x7F);
    }

    public function copy(self $src, int $dstX, int $dstY, int $srcX, int $srcY, int $w, int $h): void
    {
        imagecopy($this->resource, $src->getResource(), $dstX, $dstY, $srcX, $srcY, $w, $h);
    }

    public function copyResampled(self $src, int $dstX, int $dstY, int $srcX, int $srcY, int $dstW, int $dstH, int $srcW, int $srcH): void
    {
        imagecopyresampled($this->resource, $src->getResource(), $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
    }

    public function mergeAlpha(self $src, int $dstX, int $dstY, int $w, int $h): void
    {
        imagecopy($this->resource, $src->getResource(), $dstX, $dstY, 0, 0, $w, $h);
    }

    public function ttfText(float $size, float $angle, int $x, int $y, int $color, string $fontPath, string $text): void
    {
        imagettftext($this->resource, $size, $angle, $x, $y, $color, $fontPath, $text);
    }

    public function rotate(float $angle, int $backgroundColor): self
    {
        $rotated = imagerotate($this->resource, $angle, $backgroundColor);
        if ($rotated === false) {
            throw new ResourceException('图像旋转失败');
        }
        $c = new self(imagesx($rotated), imagesy($rotated));
        imagecopy($c->resource, $rotated, 0, 0, 0, 0, imagesx($rotated), imagesy($rotated));
        imagedestroy($rotated);
        return $c;
    }

    public function crop(int $x, int $y, int $w, int $h): self
    {
        $cropped = imagecrop($this->resource, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        if ($cropped === false) {
            throw new ResourceException('图像裁剪失败');
        }
        $c = new self($w, $h);
        imagecopy($c->resource, $cropped, 0, 0, 0, 0, $w, $h);
        imagedestroy($cropped);
        return $c;
    }

    public function destroy(): void
    {
        if ($this->resource !== null) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}
