<?php
// src/ImageData/PngImage.php
namespace Phgors\GoCaptcha\ImageData;

final class PngImage
{
    /** @var resource|\GdImage|null */
    private $resource;
    private bool $owned;

    /**
     * @param resource|\GdImage $resource
     */
    public function __construct($resource, bool $owned = true)
    {
        $this->resource = $resource;
        $this->owned = $owned;
    }

    public function toBytes(): string
    {
        ob_start();
        imagepng($this->resource);
        return (string)ob_get_clean();
    }

    public function toBase64Data(): string
    {
        return base64_encode($this->toBytes());
    }

    public function toBase64(): string
    {
        return 'data:image/png;base64,' . $this->toBase64Data();
    }

    public function saveToFile(string $path): void
    {
        if (imagepng($this->resource, $path) === false) {
            throw new \RuntimeException('PNG 保存失败：' . $path);
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
