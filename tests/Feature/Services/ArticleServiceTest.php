<?php

namespace Tests\Feature\Services;

use App\Exceptions\ArticleVersionConflictException;
use App\Models\Article;
use App\Models\ArticleLink;
use App\Models\ArticleRedirect;
use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleServiceTest extends TestCase
{
    use RefreshDatabase;

    private ArticleService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleService::class);
        $this->user = User::factory()->create();
    }

    private function createArticle(string $title, string $body = '本文'): Article
    {
        return $this->service->create(['title' => $title, 'body' => $body], $this->user);
    }

    public function test_create_saves_first_version_and_links()
    {
        $article = $this->createArticle('Laravel', '[[Vue]] と [[PHP]]');

        $this->assertSame($this->user->id, $article->created_by);
        $this->assertSame($this->user->id, $article->updated_by);
        $this->assertSame(1, $this->service->latestVersionNumber($article));
        $this->assertEqualsCanonicalizing(
            ['vue', 'php'],
            ArticleLink::where('from_article_id', $article->id)->pluck('to_title_key')->all(),
        );
    }

    public function test_resolve_ignores_case()
    {
        $article = $this->createArticle('Laravel');

        $this->assertTrue($this->service->resolve('laravel')['article']->is($article));
        $this->assertTrue($this->service->resolve('LARAVEL')['article']->is($article));
        $this->assertNull($this->service->resolve('laravel')['redirectedFrom']);
    }

    public function test_resolve_distinguishes_voiced_kana()
    {
        $this->createArticle('ハ');

        $this->assertNull($this->service->resolve('バ')['article']);
    }

    public function test_resolve_returns_null_for_missing_or_deleted_page()
    {
        $this->createArticle('Laravel')->delete();

        $this->assertNull($this->service->resolve('Laravel')['article']);
        $this->assertNull($this->service->resolve('Symfony')['article']);
    }

    public function test_update_saves_new_version()
    {
        $article = $this->createArticle('Laravel', 'v1');
        $editor = User::factory()->create();

        $updated = $this->service->update($article, ['title' => 'Laravel', 'body' => 'v2', 'summary' => '加筆'], $editor, 1);

        $this->assertSame('v2', $updated->body);
        $this->assertSame($editor->id, $updated->updated_by);
        $this->assertSame(2, $this->service->latestVersionNumber($article));
        $this->assertSame('加筆', $article->versions()->first()->summary);
    }

    public function test_update_with_stale_version_throws_conflict()
    {
        $article = $this->createArticle('Laravel', 'v1');
        $this->service->update($article, ['title' => 'Laravel', 'body' => 'v2'], $this->user, 1);

        try {
            $this->service->update($article, ['title' => 'Laravel', 'body' => '古い版からの編集'], $this->user, 1);
            $this->fail('競合が検出されなかった');
        } catch (ArticleVersionConflictException $e) {
            $this->assertSame(2, $e->latestVersion);
        }

        $this->assertSame('v2', $article->fresh()->body);
        $this->assertSame(2, $this->service->latestVersionNumber($article));
    }

    public function test_rename_registers_redirect_from_old_title()
    {
        $article = $this->createArticle('Laravel');

        $this->service->update($article, ['title' => 'Laravel 13', 'body' => '本文'], $this->user, 1);

        $resolved = $this->service->resolve('laravel');
        $this->assertTrue($resolved['article']->is($article));
        $this->assertSame('laravel', $resolved['redirectedFrom']);
    }

    public function test_changing_only_case_does_not_register_redirect()
    {
        $article = $this->createArticle('laravel');

        $this->service->update($article, ['title' => 'Laravel', 'body' => '本文'], $this->user, 1);

        $this->assertSame(0, ArticleRedirect::count());
        $this->assertSame('Laravel', $article->fresh()->title);
    }

    public function test_renaming_back_replaces_redirect()
    {
        $article = $this->createArticle('A');
        $this->service->update($article, ['title' => 'B', 'body' => '本文'], $this->user, 1);
        $this->service->update($article, ['title' => 'A', 'body' => '本文'], $this->user, 2);

        $this->assertSame(['b'], ArticleRedirect::pluck('old_title_key')->all());
        $this->assertSame('A', $this->service->resolve('B')['article']->title);
    }

    public function test_chained_renames_keep_all_old_titles_resolvable()
    {
        $article = $this->createArticle('A');
        $this->service->update($article, ['title' => 'B', 'body' => '本文'], $this->user, 1);
        $this->service->update($article, ['title' => 'C', 'body' => '本文'], $this->user, 2);

        $this->assertTrue($this->service->resolve('A')['article']->is($article));
        $this->assertTrue($this->service->resolve('B')['article']->is($article));
    }

    public function test_creating_page_with_redirected_title_removes_redirect()
    {
        $article = $this->createArticle('A');
        $this->service->update($article, ['title' => 'B', 'body' => '本文'], $this->user, 1);

        $new = $this->createArticle('a');

        $this->assertSame(0, ArticleRedirect::count());
        $this->assertTrue($this->service->resolve('A')['article']->is($new));
    }

    public function test_restore_version_saves_old_body_as_new_version()
    {
        $article = $this->createArticle('Laravel', '[[初版のリンク]]');
        $this->service->update($article, ['title' => 'Laravel', 'body' => '二版'], $this->user, 1);

        $restored = $this->service->restoreVersion($article, 1, $this->user);

        $this->assertSame('[[初版のリンク]]', $restored->body);
        $this->assertSame(3, $this->service->latestVersionNumber($article));
        $this->assertSame('v1 の内容に復元', $article->versions()->first()->summary);
        $this->assertSame(['初版のリンク'], ArticleLink::pluck('to_title_key')->all());
    }

    public function test_existing_link_keys_include_pages_and_redirects()
    {
        $this->createArticle('Laravel');
        $renamed = $this->createArticle('Old');
        $this->service->update($renamed, ['title' => 'New', 'body' => '本文'], $this->user, 1);

        $keys = $this->service->existingLinkKeys('[[laravel]] [[Old]] [[未作成]]');

        $this->assertEqualsCanonicalizing(['laravel', 'old'], $keys);
    }

    public function test_backlinks_include_links_to_old_titles()
    {
        $target = $this->createArticle('Old');
        $this->createArticle('リンク元1', '[[old]]');
        $this->service->update($target, ['title' => 'New', 'body' => '[[New]] 自己リンク'], $this->user, 1);
        $this->createArticle('リンク元2', '[[New]]');
        $this->createArticle('無関係', '[[別ページ]]');
        $this->createArticle('削除済み', '[[New]]')->delete();

        $this->assertSame(
            ['リンク元1', 'リンク元2'],
            array_column($this->service->backlinks($target->fresh()), 'title'),
        );
    }

    public function test_create_home_page_creates_linked_tutorial_pages()
    {
        $home = $this->service->createHomePage($this->user, 'My Wiki');

        $this->assertTrue($this->service->isHome($home));
        $this->assertStringContainsString('# My Wiki', $home->body);
        $this->assertSame(4, Article::count());

        // ホームからリンクされるページがすべて実在する（赤リンクにならない）
        $linked = array_map(fn (string $t) => Article::titleKey($t), $this->service->extractLinkTitles($home->body));
        $this->assertEqualsCanonicalizing($linked, $this->service->existingLinkKeys($home->body));
    }

    public function test_create_home_page_without_samples_creates_only_home_without_links()
    {
        $home = $this->service->createHomePage($this->user, 'My Wiki', withSamples: false);

        $this->assertTrue($this->service->isHome($home));
        $this->assertStringContainsString('# My Wiki', $home->body);
        $this->assertSame(1, Article::count());
        $this->assertSame([], $this->service->extractLinkTitles($home->body));
    }
}
