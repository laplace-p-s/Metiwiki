<?php

namespace App\Rules;

use App\Models\Article;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ページタイトルとして使える文字列か（正規化済みの値を検証する）。
 *
 * タイトルはそのまま URL（/{title}）になるため、URL やシステム画面と衝突するものを禁止する。
 */
class ArticleTitle implements ValidationRule
{
    /**
     * 予約済みのタイトル（照合キー）。
     *
     * - public/ 直下の実ファイル・ディレクトリと同名のもの。Web サーバーが Laravel より先に返して
     *   しまい、ページに到達できない。hot（Vite 開発サーバー）と storage（storage:link）は
     *   環境によって作られるもの。.htaccess と index.php は下の先頭ピリオド・.php の規則でも弾かれる
     * - home: トップ（/）がホームページなので、英語の「home」という別ページを作らせない
     */
    public const RESERVED_KEYS = [
        'apple-touch-icon.png',
        'build',
        'favicon.ico',
        'favicon.svg',
        'home',
        'hot',
        'robots.txt',
        'storage',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        // / は URL のパス区切り、? # はクエリ・フラグメント、[ ] | は WikiLink 記法と衝突する
        if (preg_match('/[\/?#\[\]|]/u', $value)) {
            $fail('タイトルに / ? # [ ] | は使用できません。');

            return;
        }

        if (preg_match('/\p{C}/u', $value)) {
            $fail('タイトルに制御文字は使用できません。');

            return;
        }

        // 先頭のピリオドは、. と .. をブラウザが URL として正規化してしまうほか、
        // ドットファイルへのアクセスを拒否する Web サーバー設定（Laravel 公式の nginx 設定例など）で 403 になる
        if (str_starts_with($value, '.')) {
            $fail('タイトルの先頭にピリオドは使用できません。');

            return;
        }

        $key = Article::titleKey($value);

        // .php で終わる URL を PHP-FPM へ直接渡す Web サーバー設定があり、Laravel まで届かないことがある
        if (str_ends_with($key, '.php')) {
            $fail('タイトルの末尾に .php は使用できません。');

            return;
        }

        // - はシステム画面（/-/...）の区切り
        if ($value === '-' || in_array($key, self::RESERVED_KEYS, true)) {
            $fail('このタイトルは予約されているため使用できません。');
        }
    }
}
