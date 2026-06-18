<?php
// src/Slide/GraphImage.php
namespace Phgors\GoCaptcha\Slide;

final class GraphImage
{
    private string $overlayPath;
    private string $maskPath;
    private string $shadowPath;

    public function __construct(string $overlayPath, string $maskPath, string $shadowPath)
    {
        $this->overlayPath = $overlayPath;
        $this->maskPath = $maskPath;
        $this->shadowPath = $shadowPath;
    }

    public function getOverlayPath(): string { return $this->overlayPath; }
    public function getMaskPath(): string { return $this->maskPath; }
    public function getShadowPath(): string { return $this->shadowPath; }
}
