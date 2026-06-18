<?php
namespace Phgors\GoCaptcha\Assets;

final class DefaultAssets
{
    private static ?AssetLoader $loader = null;

    public static function getLoader(): AssetLoader
    {
        if (self::$loader === null) {
            self::$loader = new AssetLoader();
        }
        return self::$loader;
    }

    /**
     * @return string[]
     */
    public static function backgrounds(): array
    {
        return self::getLoader()->listFiles('backgrounds', 'jpg');
    }

    /**
     * @return Font[]
     */
    public static function fonts(): array
    {
        $files = self::getLoader()->listFiles('fonts', 'ttf', 'otf');
        $fonts = [];
        foreach ($files as $f) {
            $fonts[] = new Font($f);
        }
        return $fonts;
    }

    /**
     * @return string[]
     */
    public static function chineseChars(): array
    {
        $chars = require self::getLoader()->requireFile('chars.php');
        return $chars['chinese'] ?? [];
    }

    /**
     * @return string[]
     */
    public static function alnumChars(): array
    {
        $chars = require self::getLoader()->requireFile('chars.php');
        return $chars['alnum'] ?? [];
    }

    /**
     * @return string[] 每个元素是 tiles 目录下的子目录路径（含 overlay/mask/shadow）
     */
    public static function tileSets(): array
    {
        $dir = self::getLoader()->getResourcesDir() . '/tiles';
        if (!is_dir($dir)) {
            return [];
        }
        $sets = [];
        foreach ((glob($dir . '/*', GLOB_ONLYDIR) ?: []) as $d) {
            $sets[] = $d;
        }
        return $sets;
    }
}
