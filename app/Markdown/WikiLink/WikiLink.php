<?php

namespace App\Markdown\WikiLink;

use App\Models\Article;
use League\CommonMark\Node\Inline\AbstractInline;

/**
 * `[[ページ名]]` / `[[表示|ページ名]]` のリンク。表示テキストは子の Text ノードに持つ。
 *
 * リンク先が実在するかは描画前に一括で調べ、resolveTo() で正式タイトルを設定する
 * （未設定は赤リンク）。
 */
class WikiLink extends AbstractInline
{
    private ?string $resolvedTitle = null;

    /**
     * @param  string  $target  正規化済みのリンク先タイトル
     */
    public function __construct(private readonly string $target)
    {
        parent::__construct();
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    public function getTargetKey(): string
    {
        return Article::titleKey($this->target);
    }

    public function exists(): bool
    {
        return $this->resolvedTitle !== null;
    }

    /**
     * リンク先ページの正式タイトルを設定する（大文字小文字の違いや改名前タイトルを解決したもの）。
     * null はページが無いことを表す。
     */
    public function resolveTo(?string $title): void
    {
        $this->resolvedTitle = $title;
    }

    /** リンク先として使うタイトル。ページがあれば正式タイトル、無ければ書かれたまま */
    public function getHrefTitle(): string
    {
        return $this->resolvedTitle ?? $this->target;
    }
}
