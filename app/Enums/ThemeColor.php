<?php

namespace App\Enums;

/**
 * サイト全体のテーマ色（サイト設定）。色の定義は resources/css/app.css の [data-theme] にある。
 * 候補は有彩色 12 色に、スターターキット既定の黒（neutral）を加えたもの
 */
enum ThemeColor: string
{
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Violet = 'violet';
    case Pink = 'pink';
    case Rose = 'rose';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';
    case Green = 'green';
    case Teal = 'teal';
    case Cyan = 'cyan';
    case Slate = 'slate';
    case Neutral = 'neutral';

    public const DEFAULT = self::Blue;

    public function label(): string
    {
        return match ($this) {
            self::Blue => '青',
            self::Indigo => 'インディゴ',
            self::Violet => 'バイオレット',
            self::Pink => 'ピンク',
            self::Rose => 'ローズ',
            self::Red => '赤',
            self::Orange => 'オレンジ',
            self::Amber => 'アンバー',
            self::Green => '緑',
            self::Teal => 'ティール',
            self::Cyan => 'シアン',
            self::Slate => 'スレート',
            self::Neutral => '黒',
        };
    }

    /**
     * 画面の選択肢
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $color) => ['value' => $color->value, 'label' => $color->label()], self::cases());
    }
}
