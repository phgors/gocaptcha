<?php
// tests/ImageData/ImageDataTest.php
namespace Phgors\GoCaptcha\Tests\ImageData;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;
use PHPUnit\Framework\TestCase;

class ImageDataTest extends TestCase
{
    private function makeGd()
    {
        $res = imagecreatetruecolor(20, 10);
        imagecolorallocate($res, 255, 0, 0);
        return $res;
    }

    public function test_jpeg_to_base64_has_data_uri_prefix(): void
    {
        $img = new JpegImage($this->makeGd());
        self::assertStringStartsWith('data:image/jpeg;base64,', $img->toBase64());
        $img->destroy();
    }

    public function test_jpeg_to_base64_data_is_raw_base64(): void
    {
        $img = new JpegImage($this->makeGd());
        self::assertSame($img->toBase64Data(), base64_encode($img->toBytes()));
        $img->destroy();
    }

    public function test_png_to_base64_has_data_uri_prefix(): void
    {
        $img = new PngImage($this->makeGd());
        self::assertStringStartsWith('data:image/png;base64,', $img->toBase64());
        $img->destroy();
    }

    public function test_jpeg_bytes_start_with_magic(): void
    {
        $img = new JpegImage($this->makeGd());
        $bytes = $img->toBytes();
        self::assertSame("\xFF\xD8", substr($bytes, 0, 2));
        $img->destroy();
    }

    public function test_png_bytes_start_with_magic(): void
    {
        $img = new PngImage($this->makeGd());
        $bytes = $img->toBytes();
        self::assertSame("\x89PNG", substr($bytes, 0, 4));
        $img->destroy();
    }

    public function test_save_to_file(): void
    {
        $path = __DIR__ . '/../fixtures/out_test.png';
        $img = new PngImage($this->makeGd());
        $img->saveToFile($path);
        self::assertFileExists($path);
        unlink($path);
        $img->destroy();
    }
}
