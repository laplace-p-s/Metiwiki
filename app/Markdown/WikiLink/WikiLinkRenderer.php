<?php

namespace App\Markdown\WikiLink;

use App\Support\ArticleUrl;
use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * WikiLink を `<a>` に描画する。リンク先が無いページは赤リンク（wikilink-new）にし、
 * 閲覧 URL（「まだ存在しません／作成する」が出る）へ向ける。
 */
class WikiLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        if (! $node instanceof WikiLink) {
            throw new InvalidArgumentException('Incompatible node type: '.$node::class);
        }

        $title = $node->getHrefTitle();

        // 実在するページは正式タイトルの URL へ直接リンクする（表記ゆれ・改名前タイトルのリダイレクトを経由しない）
        $attributes = [
            'href' => ArticleUrl::show($title),
            'class' => $node->exists() ? 'wikilink' : 'wikilink wikilink-new',
            'title' => $node->exists() ? $title : "{$title}（未作成）",
        ];

        // 未作成ページへのリンクは検索エンジンに辿らせない
        if (! $node->exists()) {
            $attributes['rel'] = 'nofollow';
        }

        return new HtmlElement('a', $attributes, $childRenderer->renderNodes($node->children()));
    }
}
