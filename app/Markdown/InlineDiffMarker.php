<?php

namespace App\Markdown;

use App\Support\SequenceDiff;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * 対応する 2 つのブロック（描画済みの HTML 断片）の文字を比べ、違う部分だけを <mark> で囲む。
 *
 * 比べる単位は、英数字の連なり・空白の連なり・それ以外の 1 文字（日本語は 1 文字ずつ）。
 * 太字やリンクをまたぐ変更も扱えるよう、HTML を解析してテキストノードだけに印を入れる。
 * 文字が同じ（書式やリンク先だけの変更）か、違いが大きすぎて印が読みにくくなるときは null を返し、
 * 呼び出し側でブロック全体を強調する。
 */
class InlineDiffMarker
{
    /** 違う部分を囲む <mark> のクラス（resources/css/app.css で強調する） */
    public const MARK_CLASS = 'wiki-diff-mark';

    /** 文字の比較の表の大きさの上限（単位の数の積）。超えるとブロック全体の強調にする */
    private const MAX_CELLS = 1_000_000;

    /** 変わった文字（空白を除く）の割合の上限。超えるとブロック全体の強調にする */
    private const MAX_CHANGED_RATIO = 0.6;

    /**
     * @return array{0: string, 1: string}|null [印を入れた古い断片, 印を入れた新しい断片]
     */
    public function mark(string $oldHtml, string $newHtml): ?array
    {
        [$oldDom, $oldRoot] = $this->load($oldHtml);
        [$newDom, $newRoot] = $this->load($newHtml);

        $oldTokens = $this->tokens($oldRoot);
        $newTokens = $this->tokens($newRoot);

        [$keptOld, $keptNew, $complete] = SequenceDiff::commonIndexes(
            array_column($oldTokens, 'text'),
            array_column($newTokens, 'text'),
            self::MAX_CELLS,
        );

        if (! $complete) {
            return null;
        }

        $changedOld = array_diff_key($oldTokens, $keptOld);
        $changedNew = array_diff_key($newTokens, $keptNew);

        $changedChars = $this->visibleLength($changedOld) + $this->visibleLength($changedNew);
        $totalChars = $this->visibleLength($oldTokens) + $this->visibleLength($newTokens);

        // 文字が同じ（書式・リンク先・画像だけの変更）か、ほとんど書き換えたとき
        if ($changedChars === 0 || $changedChars > $totalChars * self::MAX_CHANGED_RATIO) {
            return null;
        }

        $this->wrap($oldDom, $changedOld);
        $this->wrap($newDom, $changedNew);

        return [$this->serialize($oldDom, $oldRoot), $this->serialize($newDom, $newRoot)];
    }

    /**
     * @return array{0: DOMDocument, 1: DOMElement}
     */
    private function load(string $html): array
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // 文字コードの指定が無いと ISO-8859-1 として読まれるため、XML 宣言で UTF-8 を伝える
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $dom->encoding = 'UTF-8';

        foreach ($dom->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return [$dom, $child];
            }
        }

        // 解析に失敗することは通常無いが、空の断片として扱う
        $root = $dom->createElement('div');
        $dom->appendChild($root);

        return [$dom, $root];
    }

    /**
     * テキストノードを比較の単位に分ける
     *
     * @return list<array{text: string, node: DOMText, offset: int}> offset はノード内のバイト位置
     */
    private function tokens(DOMElement $root): array
    {
        $tokens = [];

        foreach ($this->textNodes($root) as $node) {
            preg_match_all('/[A-Za-z0-9_]+|\s+|./su', $node->data, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [$text, $offset]) {
                $tokens[] = ['text' => $text, 'node' => $node, 'offset' => $offset];
            }
        }

        return $tokens;
    }

    /**
     * @return list<DOMText>
     */
    private function textNodes(DOMNode $node): array
    {
        $nodes = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $nodes[] = $child;
            } elseif ($child->hasChildNodes()) {
                array_push($nodes, ...$this->textNodes($child));
            }
        }

        return $nodes;
    }

    /**
     * @param  array<int, array{text: string, node: DOMText, offset: int}>  $tokens
     */
    private function visibleLength(array $tokens): int
    {
        return array_sum(array_map(
            fn (array $token) => mb_strlen((string) preg_replace('/\s+/u', '', $token['text'])),
            $tokens,
        ));
    }

    /**
     * 変わった単位をテキストノードごとにまとめ、連続する範囲を <mark> で囲む。空白だけの範囲は囲まない
     *
     * @param  array<int, array{text: string, node: DOMText, offset: int}>  $changed
     */
    private function wrap(DOMDocument $dom, array $changed): void
    {
        /** @var array<int, array{node: DOMText, ranges: list<array{0: int, 1: int}>}> $byNode */
        $byNode = [];

        foreach ($changed as $token) {
            $id = spl_object_id($token['node']);
            $byNode[$id] ??= ['node' => $token['node'], 'ranges' => []];
            $ranges = &$byNode[$id]['ranges'];

            $start = $token['offset'];
            $end = $start + strlen($token['text']);
            $last = count($ranges) - 1;

            if ($last >= 0 && $ranges[$last][1] === $start) {
                $ranges[$last][1] = $end;
            } else {
                $ranges[] = [$start, $end];
            }

            unset($ranges);
        }

        foreach ($byNode as ['node' => $node, 'ranges' => $ranges]) {
            $data = $node->data;
            $pieces = [];
            $cursor = 0;

            foreach ($ranges as [$start, $end]) {
                $text = substr($data, $start, $end - $start);
                if (trim($text) === '') {
                    continue;
                }

                if ($start > $cursor) {
                    $pieces[] = $dom->createTextNode(substr($data, $cursor, $start - $cursor));
                }

                $mark = $dom->createElement('mark');
                $mark->setAttribute('class', self::MARK_CLASS);
                $mark->appendChild($dom->createTextNode($text));
                $pieces[] = $mark;
                $cursor = $end;
            }

            if ($pieces === []) {
                continue;
            }

            if ($cursor < strlen($data)) {
                $pieces[] = $dom->createTextNode(substr($data, $cursor));
            }

            foreach ($pieces as $piece) {
                $node->parentNode?->insertBefore($piece, $node);
            }
            $node->parentNode?->removeChild($node);
        }
    }

    private function serialize(DOMDocument $dom, DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return $html;
    }
}
