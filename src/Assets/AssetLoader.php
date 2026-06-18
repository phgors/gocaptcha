<?php
namespace Phgors\GoCaptcha\Assets;

use Phgors\GoCaptcha\Exception\ResourceException;

final class AssetLoader
{
    private string $resourcesDir;

    public function __construct(?string $resourcesDir = null)
    {
        $this->resourcesDir = $resourcesDir ?? dirname(__DIR__, 2) . '/resources';
    }

    public function getResourcesDir(): string
    {
        return $this->resourcesDir;
    }

    /**
     * @return string[]
     */
    public function listFiles(string $subDir, string $extension): array
    {
        $dir = $this->resourcesDir . '/' . trim($subDir, '/');
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        foreach (glob($dir . '/*.' . $extension) as $file) {
            $result[] = $file;
        }
        return $result;
    }

    public function requireFile(string $subPath): string
    {
        $full = $this->resourcesDir . '/' . ltrim($subPath, '/');
        if (!file_exists($full)) {
            throw new ResourceException('资源文件不存在：' . $subPath);
        }
        return $full;
    }
}
