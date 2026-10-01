<?php

namespace Tests\Feature\Admin;

use App\Enums\VersionKind;
use App\Exceptions\ArticleTitleTakenException;
use App\Models\Article;
use App\Models\ArticleRedirect;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\ArticleUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeletedArticleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ArticleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->service = app(ArticleService::class);
    }

    private function deletedArticle(string $title = '古いページ', string $body = '本文'): Article
    {
        $article = $this->service->create(['title' => $title, 'body' => $body], $this->admin);
        $this->service->delete($article, $this->admin);

        return $article;
    }

    public function test_deleted_pages_require_admin()
    {
        $article = $this->deletedArticle();
        $editor = User::factory()->create();

        $this->get(route('admin.deleted-pages.index'))->assertRedirect(route('login'));
        $this->post(route('admin.deleted-pages.restore', $article->id))->assertRedirect(route('login'));

        $this->actingAs($editor)->get(route('admin.deleted-pages.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('admin.deleted-pages.restore', $article->id))->assertForbidden();

        $this->assertSoftDeleted($article);
    }

    public function test_deleted_pages_are_listed()
    {
        $editor = User::factory()->create(['name' => '削除した人']);
        $article = $this->service->create(['title' => '古いページ', 'body' => '本文'], $this->admin);
        $this->service->delete($article, $editor, '重複のため');
        $this->service->create(['title' => '残っているページ', 'body' => '本文'], $this->admin);

        $this->actingAs($this->admin)
            ->get(route('admin.deleted-pages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/DeletedPages')
                ->where('articles.total', 1)
                ->where('articles.data.0.id', $article->id)
                ->where('articles.data.0.title', '古いページ')
                ->where('articles.data.0.deleter', '削除した人')
                ->where('articles.data.0.reason', '重複のため')
                ->where('articles.data.0.versionCount', 2)
                ->where('articles.data.0.takenBy', null)
            );
    }

    public function test_restore_is_recorded_as_version()
    {
        $article = $this->deletedArticle();
        $updatedAt = $article->fresh()->updated_at;

        $this->travel(1)->hour();
        $this->service->restoreDeleted($article, $this->admin);

        $article = $article->fresh();
        $this->assertNull($article->deleted_by);
        // 最終編集の日時は復元で変わらない
        $this->assertEquals($updatedAt, $article->updated_at);

        $version = $article->versions()->first();
        $this->assertSame(3, $version->version_number);
        $this->assertSame(VersionKind::Restore, $version->kind);
        $this->assertSame($this->admin->id, $version->user_id);
        $this->assertSame('本文', $version->body);
    }

    public function test_admin_can_restore_deleted_page()
    {
        $article = $this->deletedArticle('古いページ', '[[リンク先]]');

        $this->get('/古いページ')->assertInertia(fn (Assert $page) => $page->where('article', null));

        $this->actingAs($this->admin)
            ->post(route('admin.deleted-pages.restore', $article->id))
            ->assertRedirect(ArticleUrl::show('古いページ'));

        $this->assertNotSoftDeleted($article);

        // 作成・削除・復元の 3 版
        $this->get('/古いページ')->assertInertia(fn (Assert $page) => $page
            ->where('article.id', $article->id)
            ->where('versionCount', 3)
        );
    }

    public function test_restored_page_keeps_redirects_from_old_titles()
    {
        $article = $this->service->create(['title' => '旧名', 'body' => '本文'], $this->admin);
        $article = $this->service->update($article, ['title' => '新名', 'body' => '本文'], $this->admin, 1);
        $this->service->delete($article, $this->admin);

        $this->get('/旧名')->assertOk()->assertInertia(fn (Assert $page) => $page->where('article', null));

        $this->service->restoreDeleted($article, $this->admin);

        $this->get('/旧名')->assertRedirect(ArticleUrl::show('新名'));
    }

    public function test_restore_removes_redirect_that_the_page_would_hide()
    {
        $article = $this->deletedArticle('ページA');

        // 削除中に別ページを「ページA」から改名した（ページA → 別ページのリダイレクトができる）
        $other = $this->service->create(['title' => 'ページA', 'body' => '別の本文'], $this->admin);
        $this->service->update($other, ['title' => '別ページ', 'body' => '別の本文'], $this->admin, 1);
        $this->assertTrue(ArticleRedirect::where('old_title_key', Article::titleKey('ページA'))->exists());

        $this->service->restoreDeleted($article, $this->admin);

        $this->assertFalse(ArticleRedirect::where('old_title_key', Article::titleKey('ページA'))->exists());
        $this->get('/ページA')->assertInertia(fn (Assert $page) => $page->where('article.id', $article->id));
    }

    public function test_cannot_restore_when_title_is_taken()
    {
        $article = $this->deletedArticle('Laravel');
        $existing = $this->service->create(['title' => 'laravel', 'body' => '作り直した'], $this->admin);

        $this->actingAs($this->admin)
            ->get(route('admin.deleted-pages.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles.data.0.takenBy.title', 'laravel')
                ->where('articles.data.0.takenBy.url', '/laravel')
            );

        $this->actingAs($this->admin)
            ->from(route('admin.deleted-pages.index'))
            ->post(route('admin.deleted-pages.restore', $article->id))
            ->assertRedirect(route('admin.deleted-pages.index'))
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSoftDeleted($article);
        $this->assertNotSoftDeleted($existing);

        // 復元できなかったときは復元の版を残さない
        $this->assertSame(2, $article->versions()->count());

        $this->expectException(ArticleTitleTakenException::class);
        $this->service->restoreDeleted($article, $this->admin);
    }

    public function test_restoring_active_or_missing_page_is_404()
    {
        $active = $this->service->create(['title' => '現役', 'body' => '本文'], $this->admin);

        $this->actingAs($this->admin)->post(route('admin.deleted-pages.restore', $active->id))->assertNotFound();
        $this->actingAs($this->admin)->post(route('admin.deleted-pages.restore', 9999))->assertNotFound();
    }

    public function test_missing_page_tells_admin_about_deleted_page()
    {
        $this->deletedArticle('消したページ');
        $editor = User::factory()->create();

        $this->actingAs($this->admin)
            ->get('/消したページ')
            ->assertInertia(fn (Assert $page) => $page->where('article', null)->where('hasDeleted', true));

        $this->actingAs($editor)
            ->get('/消したページ')
            ->assertInertia(fn (Assert $page) => $page->where('hasDeleted', false));

        $this->actingAs($this->admin)
            ->get('/別のページ')
            ->assertInertia(fn (Assert $page) => $page->where('hasDeleted', false));
    }
}
