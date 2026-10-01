<?php

namespace Tests\Unit;

use App\Services\ArticleService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * タイトルの正規化と WikiLink の抽出（DB を使わない処理）
 */
class ArticleServiceTitleTest extends TestCase
{
    private ArticleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleService::class);
    }

    /** @return array<string, array{string, string}> */
    public static function titles(): array
    {
        return [
            'trim' => ['  Laravel  ', 'Laravel'],
            'underscore to space' => ['Getting_Started', 'Getting Started'],
            'collapse spaces' => ["Getting \t  Started", 'Getting Started'],
            'full-width alphanumerics' => ['ＬａｒａｖｅＬ１３', 'LaraveL13'],
            'full-width space' => ['ホーム　ページ', 'ホーム ページ'],
            'kana untouched' => ['ﾊﾞ', 'ﾊﾞ'],
        ];
    }

    #[DataProvider('titles')]
    public function test_normalize_title(string $input, string $expected)
    {
        $this->assertSame($expected, $this->service->normalizeTitle($input));
    }

    public function test_url_segment_round_trip()
    {
        $this->assertSame('Getting_Started', $this->service->titleToUrl('Getting Started'));
        $this->assertSame('Getting Started', $this->service->urlToTitle('Getting_Started'));
    }

    public function test_key_of_normalizes_and_lowercases()
    {
        $this->assertSame('getting started', $this->service->keyOf(' Getting_STARTED '));
    }

    public function test_extract_link_titles()
    {
        $body = <<<'MD'
        [[Laravel]] と [[フレームワーク|Laravel]] と [[表示|Vue_3]]
        [[laravel]] は大文字小文字違いの重複
        MD;

        $this->assertSame(['Laravel', 'Vue 3'], $this->service->extractLinkTitles($body));
    }

    public function test_extract_link_titles_ignores_code()
    {
        $body = <<<'MD'
        `[[インライン]]` と ``[[二重バッククォート]]``

        ```
        [[フェンス内]]
        ```

        ~~~
        [[チルダのフェンス内]]
        ~~~

            [[インデントのコードブロック]]

        [[本文]]
        MD;

        $this->assertSame(['本文'], $this->service->extractLinkTitles($body));
    }

    public function test_extract_link_titles_ignores_invalid_syntax()
    {
        $body = '[[]] [[|]] [[|ページ]] [[表示|]] [[ ]]';

        $this->assertSame([], $this->service->extractLinkTitles($body));
    }

    public function test_nested_brackets_end_at_first_closing()
    {
        // ネストは未対応。最初に閉じた ]] で終わるため、内側の [[bar]] だけがリンクになる
        $this->assertSame(['bar'], $this->service->extractLinkTitles('[[foo [[bar]]]]'));
    }
}
