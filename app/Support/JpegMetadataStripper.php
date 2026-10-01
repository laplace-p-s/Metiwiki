<?php

namespace App\Support;

use RuntimeException;

/**
 * JPEG から撮影位置（GPS）などのメタデータを取り除く。GD などの拡張に依存しない純 PHP 実装。
 *
 * 画像データ本体（SOS 以降）には手を付けず、ヘッダー部のセグメントだけを取捨する。
 * - 残す: APP0（JFIF）・APP2（ICC カラープロファイル）・APP14（Adobe。CMYK の解釈に必要）と、
 *   画像の復号に必要な DQT・SOF・DHT・DRI など
 * - 除く: APP1（Exif・XMP）・APP3〜APP13（IPTC など）・APP15・COM（コメント）
 * - Exif の向き（Orientation）だけは、最小限の Exif を作り直して残す（スマートフォンの写真が横倒しにならないように）
 */
class JpegMetadataStripper
{
    private const SOI = "\xFF\xD8";

    private const MARKER_SOS = 0xDA;

    private const MARKER_EOI = 0xD9;

    private const MARKER_COM = 0xFE;

    private const MARKER_APP0 = 0xE0;

    private const MARKER_APP1 = 0xE1;

    private const MARKER_APP2 = 0xE2;

    private const MARKER_APP14 = 0xEE;

    private const TAG_ORIENTATION = 0x0112;

    /**
     * @throws RuntimeException JPEG として解析できなかったとき（メタデータを消せたか保証できないため）
     */
    public static function strip(string $jpeg): string
    {
        if (! str_starts_with($jpeg, self::SOI)) {
            throw new RuntimeException('JPEG ではありません。');
        }

        $length = strlen($jpeg);
        $offset = 2;
        /** @var list<string> $segments 残すセグメント（マーカーを含む） */
        $segments = [];
        $orientation = null;

        while ($offset < $length) {
            if ($jpeg[$offset] !== "\xFF") {
                throw new RuntimeException('JPEG のマーカーを読み取れません。');
            }

            // マーカーの前には詰め物の 0xFF が続くことがある
            while ($offset < $length && $jpeg[$offset] === "\xFF") {
                $offset++;
            }

            if ($offset >= $length) {
                break;
            }

            $marker = ord($jpeg[$offset]);
            $offset++;

            // 長さを持たない単独のマーカー（RST0〜7・TEM）
            if (($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $segments[] = "\xFF".chr($marker);

                continue;
            }

            if ($marker === self::MARKER_EOI) {
                return self::assemble($segments, $orientation, "\xFF\xD9");
            }

            if ($offset + 2 > $length) {
                throw new RuntimeException('JPEG が途中で終わっています。');
            }

            $segmentLength = self::readInt('n', substr($jpeg, $offset, 2));

            if ($segmentLength === null || $segmentLength < 2 || $offset + $segmentLength > $length) {
                throw new RuntimeException('JPEG のセグメント長が不正です。');
            }

            $payload = substr($jpeg, $offset + 2, $segmentLength - 2);
            $segment = "\xFF".chr($marker).substr($jpeg, $offset, $segmentLength);
            $offset += $segmentLength;

            if ($marker === self::MARKER_SOS) {
                // ここから先は画像データ。そのまま残す
                return self::assemble($segments, $orientation, $segment.substr($jpeg, $offset));
            }

            if ($marker === self::MARKER_APP1) {
                $orientation ??= self::readOrientation($payload);

                continue;
            }

            $isApp = $marker >= self::MARKER_APP0 && $marker <= 0xEF;
            $keepApp = in_array($marker, [self::MARKER_APP0, self::MARKER_APP2, self::MARKER_APP14], true);

            if (($isApp && ! $keepApp) || $marker === self::MARKER_COM) {
                continue;
            }

            $segments[] = $segment;
        }

        throw new RuntimeException('JPEG の画像データが見つかりません。');
    }

    /**
     * SOI・残したセグメント・向きの Exif・残り（画像データ）を組み立てる。
     * JFIF の決まりで APP0 は SOI の直後に置くため、向きの Exif はその後ろに入れる
     *
     * @param  list<string>  $segments
     */
    private static function assemble(array $segments, ?int $orientation, string $rest): string
    {
        $exif = self::orientationSegment($orientation);

        if (isset($segments[0]) && str_starts_with($segments[0], "\xFF".chr(self::MARKER_APP0))) {
            array_splice($segments, 1, 0, [$exif]);
        } else {
            array_unshift($segments, $exif);
        }

        return self::SOI.implode('', $segments).$rest;
    }

    /**
     * Exif（APP1）から向き（Orientation）を読む。読めなければ null
     */
    public static function readOrientation(string $app1Payload): ?int
    {
        if (! str_starts_with($app1Payload, "Exif\0\0")) {
            return null;
        }

        $tiff = substr($app1Payload, 6);
        $format = match (substr($tiff, 0, 2)) {
            'II' => ['n' => 'v', 'N' => 'V'],   // リトルエンディアン
            'MM' => ['n' => 'n', 'N' => 'N'],   // ビッグエンディアン
            default => null,
        };

        if ($format === null || strlen($tiff) < 8) {
            return null;
        }

        $ifdOffset = self::readInt($format['N'], substr($tiff, 4, 4));

        if ($ifdOffset === null || $ifdOffset + 2 > strlen($tiff)) {
            return null;
        }

        $count = self::readInt($format['n'], substr($tiff, $ifdOffset, 2)) ?? 0;

        for ($i = 0; $i < $count; $i++) {
            $entry = substr($tiff, $ifdOffset + 2 + $i * 12, 12);

            if (strlen($entry) < 12) {
                return null;
            }

            if (self::readInt($format['n'], substr($entry, 0, 2)) === self::TAG_ORIENTATION) {
                $value = self::readInt($format['n'], substr($entry, 8, 2));

                return $value !== null && $value >= 1 && $value <= 8 ? $value : null;
            }
        }

        return null;
    }

    /**
     * 符号なし整数を 1 つ読む（n・v は 2 バイト、N・V は 4 バイト）。バイト数が足りなければ null
     */
    private static function readInt(string $format, string $bytes): ?int
    {
        $size = in_array($format, ['n', 'v'], true) ? 2 : 4;

        if (strlen($bytes) < $size) {
            return null;
        }

        $values = unpack($format, $bytes);

        return $values === false ? null : (int) $values[1];
    }

    /**
     * 向き（Orientation）だけを持つ最小限の Exif セグメント。向きが無い・既定（1）なら空
     */
    private static function orientationSegment(?int $orientation): string
    {
        if ($orientation === null || $orientation === 1) {
            return '';
        }

        // TIFF ヘッダー（ビッグエンディアン）+ IFD0（エントリ 1 件: Orientation, SHORT, 1 個）+ 次の IFD なし
        $tiff = 'MM'.pack('n', 42).pack('N', 8)
            .pack('n', 1)
            .pack('n', self::TAG_ORIENTATION).pack('n', 3).pack('N', 1).pack('n', $orientation).pack('n', 0)
            .pack('N', 0);

        $payload = "Exif\0\0".$tiff;

        return "\xFF".chr(self::MARKER_APP1).pack('n', strlen($payload) + 2).$payload;
    }
}
