<?php
namespace Phgors\GoCaptcha\Assets;

final class Font
{
    private string $path;
    private int $size;

    public function __construct(string $path, int $size = 24)
    {
        $this->path = $path;
        $this->size = $size;
    }

    public function getPath(): string { return $this->path; }
    public function getSize(): int { return $this->size; }
}
