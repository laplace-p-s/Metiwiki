<?php

namespace Tests\Unit;

use App\Support\JpegMetadataStripper;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JpegMetadataStripperTest extends TestCase
{
    private static function segment(int $marker, string $payload): string
    {
        return "\xFF".chr($marker).pack('n', strlen($payload) + 2).$payload;
    }

    /**
     * 向き（Orientation）と GPS 情報を持つ Exif（リトルエンディアン）
     */
    public static function exifWithGps(int $orientation): string
    {
        // IFD0: Orientation と GPS IFD へのポインタ。その後ろに GPS らしき秘密の値を置く
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 2)
            .pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', $orientation).pack('v', 0)
            .pack('v', 0x8825).pack('v', 4).pack('V', 1).pack('V', 38)
            .pack('V', 0)
            .'SECRET-GPS-35.6812N-139.7671E';

        return self::segment(0xE1, "Exif\0\0".$tiff);
    }

    /**
     * メタデータをたくさん含む JPEG（画像データ部はダミー）
     */
    public static function jpegWithMetadata(int $orientation = 6): string
    {
        return "\xFF\xD8"
            .self::segment(0xE0, "JFIF\0\x01\x01\0\0\x01\0\x01\0\0")
            .self::exifWithGps($orientation)
            .self::segment(0xE1, "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta>SECRET-XMP</x:xmpmeta>")
            .self::segment(0xED, "Photoshop 3.0\0SECRET-IPTC")
            .self::segment(0xFE, 'SECRET-COMMENT')
            .self::segment(0xE2, "ICC_PROFILE\0\x01\x01KEEP-ICC")
            .self::segment(0xDB, str_repeat("\x01", 65))
            .self::segment(0xDA, "\x01\x01\x00\x00\x3F\x00")
            ."IMAGE-DATA\xFF\x00MORE"
            ."\xFF\xD9";
    }

    public function test_metadata_is_removed_and_image_parts_are_kept()
    {
        $stripped = JpegMetadataStripper::strip(self::jpegWithMetadata());

        foreach (['SECRET-GPS', 'SECRET-XMP', 'SECRET-IPTC', 'SECRET-COMMENT'] as $secret) {
            $this->assertStringNotContainsString($secret, $stripped);
        }

        $this->assertStringContainsString('JFIF', $stripped);
        $this->assertStringContainsString('KEEP-ICC', $stripped);
        $this->assertStringEndsWith("IMAGE-DATA\xFF\x00MORE\xFF\xD9", $stripped);
    }

    public function test_orientation_is_kept_right_after_jfif()
    {
        $stripped = JpegMetadataStripper::strip(self::jpegWithMetadata(6));

        // SOI → APP0（JFIF）→ 向きだけの Exif（APP1）の順
        $this->assertStringStartsWith("\xFF\xD8\xFF\xE0", $stripped);
        $app0Length = unpack('n', substr($stripped, 4, 2))[1];
        $app1 = substr($stripped, 4 + $app0Length);
        $this->assertStringStartsWith("\xFF\xE1", $app1);

        $app1Length = unpack('n', substr($app1, 2, 2))[1];
        $this->assertSame(6, JpegMetadataStripper::readOrientation(substr($app1, 4, $app1Length - 2)));
    }

    public function test_default_orientation_does_not_add_exif()
    {
        $stripped = JpegMetadataStripper::strip(self::jpegWithMetadata(1));

        $this->assertStringNotContainsString('Exif', $stripped);
    }

    public function test_non_jpeg_is_rejected()
    {
        $this->expectException(RuntimeException::class);

        JpegMetadataStripper::strip("\x89PNG\r\n\x1a\n");
    }

    public function test_truncated_jpeg_is_rejected()
    {
        $this->expectException(RuntimeException::class);

        JpegMetadataStripper::strip(substr(self::jpegWithMetadata(), 0, 30));
    }

    public function test_real_jpeg_stays_decodable()
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD が無い環境では実画像で確認しない');
        }

        $image = imagecreatetruecolor(8, 6);
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        // JFIF の後ろに GPS 入りの Exif を差し込む
        $app0Length = unpack('n', substr($jpeg, 4, 2))[1];
        $withExif = substr($jpeg, 0, 4 + $app0Length).self::exifWithGps(3).substr($jpeg, 4 + $app0Length);

        $stripped = JpegMetadataStripper::strip($withExif);

        $this->assertStringNotContainsString('SECRET-GPS', $stripped);
        $this->assertSame([8, 6], array_slice((array) getimagesizefromstring($stripped), 0, 2));
        $this->assertNotFalse(imagecreatefromstring($stripped));
    }
}
