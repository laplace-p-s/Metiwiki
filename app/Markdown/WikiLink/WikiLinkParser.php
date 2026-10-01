<?php

namespace App\Markdown\WikiLink;

use App\Support\TitleNormalizer;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * WikiLink の構文解析。
 *
 * - `[[ページ名]]` はページ名をそのまま表示する
 * - `[[表示|ページ名]]` は最初の `|` で分割する（表示側に `|` は使えない）
 * - `[[]]`・`[[|]]`・`[[|ページ名]]`・`[[表示|]]` など、表示かリンク先が空の記法はリンクにしない
 * - ネストは未対応。`[` `]` を含まない最短の `[[...]]` だけを扱う（`[[foo [[bar]]]]` は bar だけがリンク）
 *
 * インライン解析の中で動くため、コードブロック・インラインコード内は自動的に対象外になる。
 */
class WikiLinkParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[([^\[\]]+?)\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$inner] = $inlineContext->getSubMatches();

        $pos = mb_strpos($inner, '|');
        if ($pos === false) {
            $display = trim($inner);
            $target = $inner;
        } else {
            $display = trim(mb_substr($inner, 0, $pos));
            $target = mb_substr($inner, $pos + 1);
        }

        $target = TitleNormalizer::normalize($target);

        if ($display === '' || $target === '') {
            return false;
        }

        $link = new WikiLink($target);
        $link->appendChild(new Text($display));

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild($link);

        return true;
    }
}
