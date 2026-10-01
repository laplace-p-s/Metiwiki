<?php

namespace App\Support;

/**
 * ページタイトルの正規化と URL セグメントとの相互変換。
 *
 * ArticleService と Markdown の WikiLink 解析の両方が使うため、依存の無いクラスにしている。
 */
class TitleNormalizer
{
    /**
     * タイトルの正規化。
     * - 前後の空白をトリム
     * - `_` をスペースと同一視
     * - 連続する空白を単一スペースに
     * - 全角英数字を半角に統一
     */
    public static function normalize(string $title): string
    {
        $title = mb_convert_kana($title, 'a');                  // 全角英数字 → 半角
        $title = str_replace('_', ' ', $title);                  // アンダースコアはスペース扱い
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;  // 連続空白を単一スペースに

        return trim($title);
    }

    /** URL セグメント（スペースは `_`）からタイトルへ復元する */
    public static function fromUrlSegment(string $segment): string
    {
        return self::normalize(str_replace('_', ' ', $segment));
    }

    /** タイトルを URL セグメントに変換する（スペース → `_`） */
    public static function toUrlSegment(string $title): string
    {
        return str_replace(' ', '_', $title);
    }
}
