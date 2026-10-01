<?php

namespace Tests\Feature\Services;

use App\Models\Article;
use App\Services\ArticleSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    private ArticleSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleSearchService::class);
    }

    /** @return list<string> */
    private function titles(string $query): array
    {
        return array_column($this->service->search($query), 'title');
    }

    public function test_empty_query_returns_nothing()
    {
        Article::factory()->create();

        $this->assertSame([], $this->service->search("  \u{3000} "));
    }

    public function test_terms_are_split_by_half_and_full_width_spaces()
    {
        $this->assertSame(['Laravel', 'Vue', 'PHP'], $this->service->terms(" Laravel\u{3000}Vue  PHP Vue "));
    }

    public function test_matches_title_or_body()
    {
        Article::factory()->create(['title' => 'Laravel 入門', 'body' => '本文']);
        Article::factory()->create(['title' => '別ページ', 'body' => 'Laravel の話']);
        Article::factory()->create(['title' => '無関係', 'body' => '本文']);

        $this->assertEqualsCanonicalizing(['Laravel 入門', '別ページ'], $this->titles('Laravel'));
    }

    public function test_all_terms_must_match()
    {
        Article::factory()->create(['title' => 'Laravel と Vue', 'body' => '組み合わせる']);
        Article::factory()->create(['title' => 'Laravel と React', 'body' => '組み合わせる']);

        $this->assertSame(['Laravel と Vue'], $this->titles('laravel vue'));
    }

    public function test_ascii_letters_are_case_insensitive()
    {
        Article::factory()->create(['title' => 'LARAVEL', 'body' => '本文']);

        $this->assertSame(['LARAVEL'], $this->titles('laravel'));
    }

    public function test_kana_are_distinguished()
    {
        Article::factory()->create(['title' => 'テスト', 'body' => 'カタカナ']);
        Article::factory()->create(['title' => 'バス', 'body' => '濁点']);

        $this->assertSame([], $this->titles('てすと'));
        $this->assertSame([], $this->titles('ハス'));
        $this->assertSame(['テスト'], $this->titles('テス'));
    }

    public function test_like_wildcards_are_escaped()
    {
        Article::factory()->create(['title' => '100%達成', 'body' => '本文']);
        Article::factory()->create(['title' => '1000達成', 'body' => '本文']);
        Article::factory()->create(['title' => 'snake_case', 'body' => '本文']);
        Article::factory()->create(['title' => 'snakeXcase', 'body' => '本文']);

        $this->assertSame(['100%達成'], $this->titles('100%'));
        $this->assertSame(['snake_case'], $this->titles('snake_case'));
    }

    public function test_deleted_pages_are_excluded()
    {
        Article::factory()->create(['title' => 'Laravel'])->delete();

        $this->assertSame([], $this->titles('Laravel'));
    }

    public function test_results_are_ordered_by_last_update()
    {
        Article::factory()->create(['title' => '古い Laravel', 'updated_at' => now()->subDay()]);
        Article::factory()->create(['title' => '新しい Laravel', 'updated_at' => now()]);

        $this->assertSame(['新しい Laravel', '古い Laravel'], $this->titles('Laravel'));
    }

    public function test_snippet_is_taken_around_the_first_hit()
    {
        $body = str_repeat('前置き', 30).'ここに検索語がある'.str_repeat('後書き', 60);
        Article::factory()->create(['title' => 'ページ', 'body' => $body]);

        $snippet = $this->service->search('検索語')[0]['snippet'];

        $this->assertStringStartsWith('…', $snippet);
        $this->assertStringContainsString('ここに検索語がある', $snippet);
        $this->assertStringEndsWith('…', $snippet);
    }

    public function test_snippet_falls_back_to_beginning_on_title_only_match()
    {
        Article::factory()->create(['title' => 'Laravel', 'body' => '先頭の文章']);

        $this->assertSame('先頭の文章', $this->service->search('Laravel')[0]['snippet']);
    }
}
