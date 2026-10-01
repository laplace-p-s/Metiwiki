<?php

namespace App\Support;

use App\Models\Article;
use App\Services\ArticleService;

/**
 * ページの URL（サイト内の相対パス）を作る。
 *
 * route() は % ? # などをエンコードせずに残すため、タイトルを含む URL には使わない
 * （「100%41」が「100A」と解釈されるなど）。タイトルは rawurlencode で完全にエンコードする。
 *
 * ページはタイトルか Article で指定する。ヘルプ（システムページ）は Article で渡したときだけ
 * /-/help 以下の URL になる（タイトル「ヘルプ」は通常のページを指す）
 */
class ArticleUrl
{
    /** 閲覧 URL。ホームはトップ（/） */
    public static function show(Article|string $page): string
    {
        if (self::isHelp($page)) {
            return self::base().'/-/help';
        }

        $title = self::title($page);

        if (Article::titleKey($title) === Article::titleKey(ArticleService::HOME_TITLE)) {
            return self::base().'/';
        }

        return self::path($title);
    }

    public static function edit(Article|string $page): string
    {
        return self::isHelp($page) ? self::base().'/-/help/edit' : self::path(self::title($page), '/-/edit');
    }

    public static function history(Article|string $page): string
    {
        return self::isHelp($page) ? self::base().'/-/help/history' : self::path(self::title($page), '/-/history');
    }

    public static function version(Article|string $page, int $version): string
    {
        return self::isHelp($page)
            ? self::base()."/-/help/history/{$version}"
            : self::path(self::title($page), "/-/history/{$version}");
    }

    /** 版の差分。版を省略すると最新版と 1 つ前の版の比較になる */
    public static function diff(Article|string $page, ?int $from = null, ?int $to = null): string
    {
        $query = http_build_query(array_filter(['from' => $from, 'to' => $to], fn ($v) => $v !== null));
        $url = self::isHelp($page) ? self::base().'/-/help/diff' : self::path(self::title($page), '/-/diff');

        return $url.($query !== '' ? '?'.$query : '');
    }

    private static function isHelp(Article|string $page): bool
    {
        return $page instanceof Article && $page->isHelp();
    }

    private static function title(Article|string $page): string
    {
        return $page instanceof Article ? $page->title : $page;
    }

    private static function path(string $title, string $suffix = ''): string
    {
        $segment = app(ArticleService::class)->titleToUrl($title);

        return self::base().'/'.rawurlencode($segment).$suffix;
    }

    /** サブディレクトリに設置された場合のベースパス */
    private static function base(): string
    {
        return request()->getBaseUrl();
    }
}
