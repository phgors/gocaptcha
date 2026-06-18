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
     * @param string ...$extensions 可变数量扩展名，向后兼容单扩展名调用
     * @return string[]
     */
    public function listFiles(string $subDir, string ...$extensions): array
    {
        $dir = $this->resourcesDir . '/' . trim($subDir, '/');
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        foreach ($extensions as $extension) {
            foreach ((glob($dir . '/*.' . $extension) ?: []) as $file) {
                $result[] = $file;
            }
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
