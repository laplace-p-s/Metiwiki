<?php

namespace Tests\Feature\Wiki;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 履歴・版・差分と特殊ページ（最近の更新・全ページ一覧・検索）
 */
class ArticlePagesTest extends TestCase
{
    use RefreshDatabase;

    private ArticleService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleService::class);
        $this->user = User::factory()->create(['name' => '編集者']);
    }

    private function createArticleWithVersions(string $title, int $versions): Article
    {
        $article = $this->service->create(['title' => $title, 'body' => 'v1'], $this->user);
        for ($i = 2; $i <= $versions; $i++) {
            $this->service->update($article, ['title' => $title, 'body' => "v{$i}", 'summary' => "{$i}版"], $this->user, $i - 1);
        }

        return $article;
    }

    public function test_history_lists_versions_newest_first()
    {
        $this->createArticleWithVersions('Laravel', 3);

        $this->get('/Laravel/-/history')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/History')
                ->where('latestVersion', 3)
                ->where('versions.data.0.version_number', 3)
                ->where('versions.data.0.summary', '3版')
                ->where('versions.data.0.user', ['id' => $this->user->id, 'name' => '編集者'])
                ->where('versions.data.0.diffUrl', '/Laravel/-/diff?from=2&to=3')
                ->where('versions.data.2.diffUrl', null)
                ->missing('versions.data.0.user.login_id')
                ->missing('versions.data.0.user.email'),
            );
    }

    public function test_specific_version_is_shown()
    {
        $this->createArticleWithVersions('Laravel', 3);

        $this->get('/Laravel/-/history/2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Version')
                ->where('version.version_number', 2)
                ->where('version.body', 'v2'),
            );

        $this->get('/Laravel/-/history/9')->assertNotFound();
    }

    public function test_diff_between_versions()
    {
        $this->createArticleWithVersions('Laravel', 3);

        $this->get('/Laravel/-/diff?from=1&to=3')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Diff')
                ->where('from.body', 'v1')
                ->where('to.body', 'v3')
                // 隣り合わない版どうしは、描画した 2 版を並べるだけで強調しない
                ->where('preview.marked', false)
                ->where('preview.fromHtml', "<p>v1</p>\n")
                ->where('preview.toHtml', "<p>v3</p>\n"),
            );
    }

    public function test_diff_of_adjacent_versions_marks_changed_blocks_in_preview()
    {
        $this->createArticleWithVersions('Laravel', 3);

        $this->get('/Laravel/-/diff?from=2&to=3')
            ->assertInertia(fn (Assert $page) => $page
                ->where('preview.marked', true)
                ->where('preview.fromHtml', "<p class=\"wiki-diff-changed\">v2</p>\n")
                ->where('preview.toHtml', "<p class=\"wiki-diff-changed\">v3</p>\n"),
            );
    }

    public function test_diff_defaults_to_latest_two_versions()
    {
        $this->createArticleWithVersions('Laravel', 3);

        $this->get('/Laravel/-/diff')
            ->assertInertia(fn (Assert $page) => $page
                ->where('from.version_number', 2)
                ->where('to.version_number', 3),
            );
    }

    public function test_diff_keeps_query_when_redirecting_to_canonical_url()
    {
        $this->createArticleWithVersions('Laravel', 3);

        $this->get('/laravel/-/diff?from=1&to=2')->assertRedirect('/Laravel/-/diff?from=1&to=2');
    }

    public function test_diff_with_missing_version_is_404()
    {
        $this->createArticleWithVersions('Laravel', 2);

        $this->get('/Laravel/-/diff?from=1&to=5')->assertNotFound();
    }

    public function test_recent_changes_lists_versions_of_live_pages()
    {
        $this->createArticleWithVersions('Laravel', 2);
        $this->createArticleWithVersions('削除済み', 1)->delete();

        $this->get('/-/recent-changes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/RecentChanges')
                ->has('versions.data', 2)
                ->where('versions.data.0.article.title', 'Laravel'),
            );
    }

    public function test_all_pages_are_sorted_ignoring_case()
    {
        foreach (['banana', 'Apple', 'cherry'] as $title) {
            $this->createArticleWithVersions($title, 1);
        }

        $this->get('/-/all-pages')
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/AllPages')
                ->where('pages.0.title', 'Apple')
                ->where('pages.1.title', 'banana')
                ->where('pages.2.title', 'cherry'),
            );
    }

    public function test_search_returns_results_with_urls()
    {
        $this->service->create(['title' => 'Getting Started', 'body' => 'Laravel の入門'], $this->user);

        $this->get('/-/search?q=入門')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Search')
                ->where('query', '入門')
                ->where('results.0.title', 'Getting Started')
                ->where('results.0.url', '/Getting_Started')
                ->where('exactMatch', false)
                ->where('createUrl', '/'.rawurlencode('入門').'/-/edit'),
            );
    }

    public function test_search_reports_exact_title_match()
    {
        $this->service->create(['title' => 'Laravel', 'body' => '本文'], $this->user);

        $this->get('/-/search?q=laravel')
            ->assertInertia(fn (Assert $page) => $page->where('exactMatch', true));
    }

    public function test_search_does_not_offer_creation_for_invalid_title()
    {
        $this->get('/-/search?q='.rawurlencode('a|b'))
            ->assertInertia(fn (Assert $page) => $page->where('createUrl', null));
    }
}
