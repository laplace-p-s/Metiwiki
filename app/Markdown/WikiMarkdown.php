<?php

namespace App\Markdown;

use App\Markdown\WikiLink\WikiLink;
use App\Markdown\WikiLink\WikiLinkExtension;
use App\Models\Article;
use App\Models\ArticleRedirect;
use App\Support\SequenceDiff;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Wiki 本文（Markdown）の解析と描画。閲覧ページも編集プレビューも同じ描画を使う。
 *
 * 生の HTML はエスケープし、javascript: などの危険なリンクは無効にするため、
 * 出力はそのまま v-html で表示してよい。
 */
class WikiMarkdown
{
    /** 見出しの id の接頭辞（画面上の他の要素の id と衝突させない）。id は「h-見出し」になる */
    public const HEADING_ID_PREFIX = 'h';

    /** renderComparison() で変わったブロックに付けるクラス（resources/css/app.css で強調する） */
    public const CHANGED_CLASS = 'wiki-diff-changed';

    /** ブロックの LCS の表の大きさの上限（ブロック数の積）。超えると中間部分は丸ごと変更扱いになる */
    private const MAX_LCS_CELLS = 250_000;

    /** 文字単位で比べるブロックの組の上限。超えると変更ブロックはすべてブロック全体の強調になる */
    private const MAX_INLINE_PAIRS = 200;

    private MarkdownParser $parser;

    private HtmlRenderer $renderer;

    public function __construct(?string $appUrl = null)
    {
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            // 極端な入れ子や強調記号の多用による解析時間の増大を防ぐ
            'max_nesting_level' => 50,
            'max_delimiters_per_line' => 1000,
            'heading_permalink' => [
                'insert' => 'none',
                'apply_id_to_heading' => true,
                'id_prefix' => self::HEADING_ID_PREFIX,
                'fragment_prefix' => self::HEADING_ID_PREFIX,
            ],
            // 外部リンクは新しいタブで開き、公開 Wiki へのスパム対策として nofollow を付ける
            'external_link' => [
                'internal_hosts' => array_filter([parse_url((string) $appUrl, PHP_URL_HOST)]),
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new HeadingPermalinkExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addExtension(new WikiLinkExtension);

        $this->parser = new MarkdownParser($environment);
        $this->renderer = new HtmlRenderer($environment);
    }

    /**
     * 本文中の WikiLink のリンク先（正規化済みタイトル）。照合キーで重複を除く。
     *
     * @return list<string>
     */
    public function linkTargets(string $markdown): array
    {
        $targets = [];
        foreach ($this->wikiLinks($this->parser->parse($markdown)) as $link) {
            $targets[$link->getTargetKey()] ??= $link->getTarget();
        }

        return array_values($targets);
    }

    /**
     * HTML と目次（見出しの一覧）を作る。リンク先の無い WikiLink は赤リンクになる。
     *
     * @return array{html: string, toc: list<array{level: int, text: string, id: string}>}
     */
    public function render(string $markdown): array
    {
        $document = $this->parser->parse($markdown);

        return [
            'html' => $this->renderResolved($document),
            'toc' => $this->tableOfContents($document),
        ];
    }

    /**
     * 2 つの版を描画し、変わった箇所に印を付ける（差分画面のプレビュー比較用）。
     *
     * 1. 最上位のブロック（段落・見出し・リスト・表・引用・コードなど）の Markdown ソースを
     *    最長共通部分列で比べ、共通部分に入らなかったブロックを変更とみなす
     * 2. 変更の塊の中で、同じ位置・同じ種類の古いブロックと新しいブロックを対応付け、
     *    文字の違う部分だけを <mark> で囲む（InlineDiffMarker）
     * 3. 対応付けられなかったブロック（丸ごとの追加・削除）と、文字の比較で扱えないブロック
     *    （書式・リンク先・画像だけの変更や、ほとんど書き換えたもの）は、ブロック全体に CHANGED_CLASS を付ける
     *
     * @return array{old: string, new: string}
     */
    public function renderComparison(string $oldMarkdown, string $newMarkdown): array
    {
        $oldDocument = $this->parser->parse($oldMarkdown);
        $newDocument = $this->parser->parse($newMarkdown);

        $oldBlocks = $this->topLevelBlocks($oldDocument, $oldMarkdown);
        $newBlocks = $this->topLevelBlocks($newDocument, $newMarkdown);

        [$keptOld, $keptNew] = SequenceDiff::commonIndexes(
            array_column($oldBlocks, 'source'),
            array_column($newBlocks, 'source'),
            self::MAX_LCS_CELLS,
        );

        $pairs = $this->pairChangedBlocks($oldBlocks, $newBlocks, $keptOld, $keptNew);
        if (count($pairs) > self::MAX_INLINE_PAIRS) {
            // 変更が多すぎるときは文字の比較をせず、ブロック全体の強調だけにする
            $pairs = [];
        }
        $pairedOld = array_flip(array_column($pairs, 0));
        $pairedNew = array_flip(array_column($pairs, 1));

        foreach ($oldBlocks as $i => $block) {
            if (! isset($keptOld[$i]) && ! isset($pairedOld[$i])) {
                $this->markBlock($block['node']);
            }
        }
        foreach ($newBlocks as $j => $block) {
            if (! isset($keptNew[$j]) && ! isset($pairedNew[$j])) {
                $this->markBlock($block['node']);
            }
        }

        $this->resolveLinks($oldDocument);
        $this->resolveLinks($newDocument);

        $oldHtml = array_map(fn (array $b) => $this->renderer->renderNodes([$b['node']]), $oldBlocks);
        $newHtml = array_map(fn (array $b) => $this->renderer->renderNodes([$b['node']]), $newBlocks);

        $marker = new InlineDiffMarker;
        foreach ($pairs as [$i, $j]) {
            $marked = $marker->mark($oldHtml[$i], $newHtml[$j]);

            if ($marked) {
                [$oldHtml[$i], $newHtml[$j]] = $marked;
            } else {
                $this->markBlock($oldBlocks[$i]['node']);
                $this->markBlock($newBlocks[$j]['node']);
                $oldHtml[$i] = $this->renderer->renderNodes([$oldBlocks[$i]['node']]);
                $newHtml[$j] = $this->renderer->renderNodes([$newBlocks[$j]['node']]);
            }
        }

        return [
            'old' => $this->joinBlocks($oldHtml),
            'new' => $this->joinBlocks($newHtml),
        ];
    }

    /**
     * 変更の塊（共通ブロックに挟まれた、古い側の連続する変更ブロックと新しい側の連続する変更ブロック）ごとに、
     * 先頭から順に同じ種類のブロックどうしを対応付ける
     *
     * @param  list<array{node: AbstractBlock, source: string}>  $oldBlocks
     * @param  list<array{node: AbstractBlock, source: string}>  $newBlocks
     * @param  array<int, true>  $keptOld
     * @param  array<int, true>  $keptNew
     * @return list<array{0: int, 1: int}>
     */
    private function pairChangedBlocks(array $oldBlocks, array $newBlocks, array $keptOld, array $keptNew): array
    {
        $pairs = [];
        $i = 0;
        $j = 0;
        $n = count($oldBlocks);
        $m = count($newBlocks);

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && isset($keptOld[$i], $keptNew[$j])) {
                $i++;
                $j++;

                continue;
            }

            $removed = [];
            while ($i < $n && ! isset($keptOld[$i])) {
                $removed[] = $i++;
            }
            $added = [];
            while ($j < $m && ! isset($keptNew[$j])) {
                $added[] = $j++;
            }

            foreach (array_map(null, $removed, $added) as [$oldIndex, $newIndex]) {
                if ($oldIndex !== null && $newIndex !== null
                    && get_class($oldBlocks[$oldIndex]['node']) === get_class($newBlocks[$newIndex]['node'])) {
                    $pairs[] = [$oldIndex, $newIndex];
                }
            }

            // 共通ブロックの片側だけが残る不整合は起きないが、無限ループを避ける
            if ($removed === [] && $added === []) {
                $i++;
                $j++;
            }
        }

        return $pairs;
    }

    /** ブロック全体を変更として強調する */
    private function markBlock(AbstractBlock $node): void
    {
        $class = $node->data->get('attributes/class', '');
        $node->data->set('attributes/class', trim((is_string($class) ? $class : '').' '.self::CHANGED_CLASS));
    }

    /**
     * ブロックごとの HTML を、文書全体を描画したときと同じ形（ブロックの区切りと末尾の区切り）につなぐ
     *
     * @param  array<int, string>  $blocks
     */
    private function joinBlocks(array $blocks): string
    {
        $separator = $this->renderer->getBlockSeparator();
        $html = implode($separator, $blocks);

        return $html === '' ? '' : $html.$separator;
    }

    /** WikiLink のリンク先を解決して HTML にする（リンク先の無い WikiLink は赤リンク） */
    private function renderResolved(Document $document): string
    {
        $this->resolveLinks($document);

        return $this->renderer->renderDocument($document)->getContent();
    }

    /** WikiLink のリンク先を正式タイトルに解決する（リンク先の無いものは赤リンクになる） */
    private function resolveLinks(Document $document): void
    {
        $links = $this->wikiLinks($document);
        $titles = $this->canonicalTitles(array_map(fn (WikiLink $l) => $l->getTargetKey(), $links));
        foreach ($links as $link) {
            $link->resolveTo($titles[$link->getTargetKey()] ?? null);
        }
    }

    /**
     * 最上位のブロックと、その Markdown ソース（比較用に行末の空白を除く）
     *
     * @return list<array{node: AbstractBlock, source: string}>
     */
    private function topLevelBlocks(Document $document, string $markdown): array
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $blocks = [];

        foreach ($document->children() as $node) {
            if (! $node instanceof AbstractBlock) {
                continue;
            }

            $start = $node->getStartLine();
            $end = $node->getEndLine();
            $source = $start !== null && $end !== null
                ? implode("\n", array_map('rtrim', array_slice($lines, $start - 1, $end - $start + 1)))
                : '';

            $blocks[] = ['node' => $node, 'source' => $source];
        }

        return $blocks;
    }

    /**
     * 照合キーのうち、ページが実在する（または改名前タイトルとしてリダイレクトがある）もの
     *
     * @param  array<int, string>  $keys
     * @return list<string>
     */
    public function existingKeys(array $keys): array
    {
        return array_map('strval', array_keys($this->canonicalTitles($keys)));
    }

    /**
     * 照合キー → リンク先ページの正式タイトル。改名前タイトルは改名後のページのタイトルになる。
     * リンクの数によらずクエリ数は一定（ページ・リダイレクト・リダイレクト先）。
     *
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    private function canonicalTitles(array $keys): array
    {
        $keys = array_values(array_unique($keys));
        if ($keys === []) {
            return [];
        }

        $titles = [];

        ArticleRedirect::whereIn('old_title_key', $keys)
            ->with('article:id,title')
            ->get(['id', 'old_title_key', 'article_id'])
            ->each(function (ArticleRedirect $r) use (&$titles) {
                if ($r->article) {
                    $titles[$r->old_title_key] = $r->article->title;
                }
            });

        // 実在ページはリダイレクトより優先する
        Article::whereIn('title_key', $keys)
            ->get(['id', 'title', 'title_key'])
            ->each(function (Article $a) use (&$titles) {
                $titles[$a->title_key] = $a->title;
            });

        return $titles;
    }

    /**
     * @return list<WikiLink>
     */
    private function wikiLinks(Document $document): array
    {
        $links = [];
        foreach ($document->iterator() as $node) {
            if ($node instanceof WikiLink) {
                $links[] = $node;
            }
        }

        return $links;
    }

    /**
     * @return list<array{level: int, text: string, id: string}>
     */
    private function tableOfContents(Document $document): array
    {
        $toc = [];
        foreach ($document->iterator() as $node) {
            if (! $node instanceof Heading) {
                continue;
            }

            $id = $node->data->get('attributes/id', '');
            $text = trim(StringContainerHelper::getChildText($node, [RawMarkupContainerInterface::class]));

            if (is_string($id) && $id !== '' && $text !== '') {
                $toc[] = ['level' => $node->getLevel(), 'text' => $text, 'id' => $id];
            }
        }

        return $toc;
    }
}
