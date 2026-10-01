<?php

namespace Tests\Feature\Markdown;

use App\Markdown\WikiMarkdown;
use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WikiMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private WikiMarkdown $markdown;

    private ArticleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://wiki.example.com']);
        $this->app->forgetInstance(WikiMarkdown::class);

        $this->markdown = app(WikiMarkdown::class);
        $this->service = app(ArticleService::class);
    }

    private function html(string $markdown): string
    {
        return $this->markdown->render($markdown)['html'];
    }

    private function createArticle(string $title): void
    {
        $this->service->create(['title' => $title, 'body' => '本文'], User::factory()->create());
    }

    public function test_existing_page_link_is_normal_link()
    {
        $this->createArticle('Getting Started');

        $this->assertSame(
            "<p><a href=\"/Getting_Started\" class=\"wikilink\" title=\"Getting Started\">getting_started</a></p>\n",
            $this->html('[[getting_started]]'),
        );
    }

    public function test_missing_page_link_is_red_link()
    {
        $html = $this->html('[[未作成]]');

        $this->assertStringContainsString('class="wikilink wikilink-new"', $html);
        $this->assertStringContainsString('rel="nofollow"', $html);
        $this->assertStringContainsString('href="/'.rawurlencode('未作成').'"', $html);
    }

    public function test_link_to_old_title_points_to_renamed_page()
    {
        $this->createArticle('Old');
        $article = $this->service->resolve('Old')['article'];
        $this->service->update($article, ['title' => 'New', 'body' => '本文'], User::factory()->create(), 1);

        $this->assertSame(
            "<p><a href=\"/New\" class=\"wikilink\" title=\"New\">Old</a></p>\n",
            $this->html('[[Old]]'),
        );
    }

    public function test_piped_link_shows_display_text()
    {
        $this->createArticle('Markdown記法');

        $html = $this->html('[[記法の早見表|Markdown記法]]');

        $this->assertStringContainsString('>記法の早見表</a>', $html);
        $this->assertStringContainsString('href="/'.rawurlencode('Markdown記法').'"', $html);
    }

    public function test_home_link_points_to_top()
    {
        $this->createArticle(ArticleService::HOME_TITLE);

        $this->assertStringContainsString('<a href="/" class="wikilink"', $this->html('[[ホーム]]'));
    }

    public function test_invalid_syntax_is_left_as_text()
    {
        $html = $this->html('[[]] [[|]] [[|ページ]] [[表示|]]');

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('[[|ページ]]', $html);
    }

    public function test_wikilink_is_not_parsed_in_code()
    {
        $html = $this->html("`[[コード]]`\n\n```\n[[フェンス]]\n```");

        $this->assertStringNotContainsString('<a', $html);
        $this->assertStringContainsString('<code>[[コード]]</code>', $html);
    }

    public function test_link_text_is_escaped()
    {
        $html = $this->html('[[<script>alert(1)</script>|ページ]]');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_raw_html_is_escaped()
    {
        $html = $this->html("<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_unsafe_links_are_removed()
    {
        $html = $this->html('[クリック](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_external_links_get_nofollow_and_new_window()
    {
        $html = $this->html('[外部](https://example.org) [内部](https://wiki.example.com/Laravel)');

        $this->assertMatchesRegularExpression('/<a rel="[^"]*\bnofollow\b[^"]*" target="_blank" href="https:\/\/example.org">外部<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<a rel="[^"]*\bnoopener\b[^"]*"[^>]*>外部/', $html);
        $this->assertStringContainsString('<a href="https://wiki.example.com/Laravel">内部</a>', $html);
    }

    public function test_gfm_extensions_are_enabled()
    {
        $html = $this->html("~~打ち消し~~\n\n- [x] 完了\n\n| a | b |\n| - | - |\n| 1 | 2 |\n\nhttps://example.org");

        $this->assertStringContainsString('<del>打ち消し</del>', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('href="https://example.org"', $html);
    }

    public function test_table_of_contents_is_built_from_headings()
    {
        $result = $this->markdown->render("# 概要\n\n## 導入 [[手順]]\n\n## 導入 [[手順]]\n\n### `code` 見出し");

        $this->assertSame([
            ['level' => 1, 'text' => '概要', 'id' => 'h-概要'],
            ['level' => 2, 'text' => '導入 手順', 'id' => 'h-導入-手順'],
            ['level' => 2, 'text' => '導入 手順', 'id' => 'h-導入-手順-1'],
            ['level' => 3, 'text' => 'code 見出し', 'id' => 'h-code-見出し'],
        ], $result['toc']);
        $this->assertStringContainsString('<h1 id="h-概要">概要</h1>', $result['html']);
    }

    public function test_link_targets_are_deduplicated_by_key()
    {
        $this->assertSame(['Laravel', 'Vue 3'], $this->markdown->linkTargets('[[Laravel]] [[laravel]] [[表示|Vue_3]]'));
    }

    public function test_query_count_does_not_grow_with_number_of_links()
    {
        $this->createArticle('A');
        $this->createArticle('B');

        $count = function (string $markdown): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->markdown->render($markdown);

            return count(DB::getQueryLog());
        };

        $this->assertSame($count('[[A]]'), $count('[[A]] [[B]] [[C]] [[D]] [[E]]'));
    }

    public function test_comparison_marks_changed_characters_and_whole_added_or_removed_blocks()
    {
        $old = "# 見出し\n\n変わらない段落\n\n古い段落\n\n- 項目\n\n消える段落";
        $new = "# 見出し\n\n変わらない段落\n\n新しい段落\n\n追加した段落\n\n- 項目";

        ['old' => $oldHtml, 'new' => $newHtml] = $this->markdown->renderComparison($old, $new);

        // 書き換えた段落は、違う文字だけに印を付ける
        $this->assertStringContainsString('<p><mark class="wiki-diff-mark">古</mark>い段落</p>', $oldHtml);
        $this->assertStringContainsString('<p><mark class="wiki-diff-mark">新し</mark>い段落</p>', $newHtml);

        // 丸ごと追加・削除した段落は、ブロック全体に印を付ける
        $this->assertStringContainsString('<p class="wiki-diff-changed">消える段落</p>', $oldHtml);
        $this->assertStringContainsString('<p class="wiki-diff-changed">追加した段落</p>', $newHtml);
        $this->assertSame(1, substr_count($oldHtml, 'wiki-diff-changed'));
        $this->assertSame(1, substr_count($newHtml, 'wiki-diff-changed'));

        // 変わらないブロックには印を付けない（見出しの id などはそのまま）
        $this->assertStringContainsString('<p>変わらない段落</p>', $newHtml);
        $this->assertStringContainsString('<h1 id="h-見出し">見出し</h1>', $newHtml);
        $this->assertStringContainsString("<ul>\n<li>項目</li>\n</ul>", $newHtml);
    }

    public function test_comparison_marks_characters_in_headings_tables_and_lists()
    {
        $this->createArticle('A');

        ['old' => $oldHtml, 'new' => $newHtml] = $this->markdown->renderComparison(
            "## 旧見出し\n\n| 列 | 値 |\n| - | - |\n| a | 1 |\n\n- りんご\n- みかん\n- ぶどう",
            "## 新見出し\n\n| 列 | 値 |\n| - | - |\n| a | 2 |\n\n- りんご\n- ばなな\n- ぶどう\n\n[[A]] と [[無い]]",
        );

        $this->assertStringContainsString('<h2 id="h-新見出し"><mark class="wiki-diff-mark">新</mark>見出し</h2>', $newHtml);
        $this->assertStringContainsString('<td><mark class="wiki-diff-mark">2</mark></td>', $newHtml);
        $this->assertStringContainsString('<td><mark class="wiki-diff-mark">1</mark></td>', $oldHtml);
        $this->assertStringContainsString('<li><mark class="wiki-diff-mark">ばなな</mark></li>', $newHtml);
        $this->assertStringContainsString('<li>りんご</li>', $newHtml);

        // 追加した段落はブロック全体。WikiLink は閲覧時と同じく解決される
        $this->assertStringContainsString('<p class="wiki-diff-changed"><a href="/A"', $newHtml);
        $this->assertStringContainsString('wikilink-new', $newHtml);
    }

    public function test_comparison_marks_across_inline_formatting()
    {
        ['old' => $oldHtml, 'new' => $newHtml] = $this->markdown->renderComparison(
            '今日は **晴れ** の予定です。',
            '今日は **雨** の予定です。',
        );

        $this->assertSame("<p>今日は <strong><mark class=\"wiki-diff-mark\">晴れ</mark></strong> の予定です。</p>\n", $oldHtml);
        $this->assertSame("<p>今日は <strong><mark class=\"wiki-diff-mark\">雨</mark></strong> の予定です。</p>\n", $newHtml);
    }

    public function test_comparison_marks_whole_words_in_english()
    {
        ['new' => $html] = $this->markdown->renderComparison(
            'The quick brown fox jumps over the lazy dog.',
            'The quick red fox jumps over the lazy dog.',
        );

        $this->assertStringContainsString('The quick <mark class="wiki-diff-mark">red</mark> fox', $html);
    }

    public function test_comparison_falls_back_to_whole_block_when_text_is_same_or_mostly_rewritten()
    {
        // 書式だけの変更（文字は同じ）
        ['new' => $formatOnly] = $this->markdown->renderComparison('大事なこと', '**大事なこと**');
        $this->assertSame("<p class=\"wiki-diff-changed\"><strong>大事なこと</strong></p>\n", $formatOnly);

        // リンク先だけの変更
        ['new' => $linkOnly] = $this->markdown->renderComparison('[説明](https://a.example/)', '[説明](https://b.example/)');
        $this->assertStringContainsString('<p class="wiki-diff-changed">', $linkOnly);

        // ほとんど書き換えた段落
        ['old' => $old, 'new' => $new] = $this->markdown->renderComparison('あいうえお', 'かきくけこ');
        $this->assertSame("<p class=\"wiki-diff-changed\">あいうえお</p>\n", $old);
        $this->assertSame("<p class=\"wiki-diff-changed\">かきくけこ</p>\n", $new);
        $this->assertStringNotContainsString('<mark', $old.$new);
    }

    public function test_comparison_does_not_pair_blocks_of_different_kinds()
    {
        ['old' => $oldHtml, 'new' => $newHtml] = $this->markdown->renderComparison('段落です', '# 段落です');

        $this->assertSame("<p class=\"wiki-diff-changed\">段落です</p>\n", $oldHtml);
        $this->assertStringContainsString('<h1 id="h-段落です" class="wiki-diff-changed">', $newHtml);
    }

    public function test_comparison_of_identical_bodies_marks_nothing()
    {
        $body = "# 見出し\n\n段落\n\n```php\necho 1;\n```";

        $result = $this->markdown->renderComparison($body, $body);

        $this->assertStringNotContainsString('wiki-diff-changed', $result['old'].$result['new']);
        $this->assertSame($this->html($body), $result['new']);
    }
}
