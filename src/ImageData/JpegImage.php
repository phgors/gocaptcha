<?php
// src/ImageData/JpegImage.php
namespace Phgors\GoCaptcha\ImageData;

final class JpegImage
{
    /** @var resource|\GdImage|null */
    private $resource;
    private bool $owned;

    /**
     * @param resource|\GdImage $resource
     * @param bool $owned 是否由本对象负责销毁（默认 true）
     */
    public function __construct($resource, bool $owned = true)
    {
        $this->resource = $resource;
        $this->owned = $owned;
    }

    public function toBytes(int $quality = 80): string
    {
        ob_start();
        imagejpeg($this->resource, null, $quality);
        return (string)ob_get_clean();
    }

    public function toBase64Data(int $quality = 80): string
    {
        return base64_encode($this->toBytes($quality));
    }

    public function toBase64(int $quality = 80): string
    {
        return 'data:image/jpeg;base64,' . $this->toBase64Data($quality);
    }

    public function saveToFile(string $path, int $quality = 80): void
    {
        if (imagejpeg($this->resource, $path, $quality) === false) {
            throw new \RuntimeException('JPEG 保存失败：' . $path);
        }
    }

    public function destroy(): void
    {
        if ($this->owned && $this->resource !== null) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}
