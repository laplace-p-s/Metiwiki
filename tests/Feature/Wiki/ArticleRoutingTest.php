<?php

namespace Tests\Feature\Wiki;

use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * タイトル＝URL のルーティング（正式 URL へのリダイレクト、未作成ページ、404）
 */
class ArticleRoutingTest extends TestCase
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

    private function createArticle(string $title, string $body = '本文'): void
    {
        $this->service->create(['title' => $title, 'body' => $body], $this->user);
    }

    public function test_top_shows_home_page()
    {
        $this->createArticle(ArticleService::HOME_TITLE, 'ようこそ');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Show')
                ->where('title', ArticleService::HOME_TITLE)
                ->where('article.body', 'ようこそ')
                ->where('isHome', true),
            );
    }

    public function test_top_shows_placeholder_before_home_page_exists()
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Show')
                ->where('article', null),
            );
    }

    public function test_home_title_url_redirects_to_top()
    {
        $this->createArticle(ArticleService::HOME_TITLE);

        $this->get('/'.rawurlencode('ホーム'))->assertRedirect('/');
    }

    public function test_page_is_shown_by_title()
    {
        $this->createArticle('Getting Started', '[[Laravel]] [[未作成]]');
        $this->createArticle('Laravel');

        $this->get('/Getting_Started')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Show')
                ->where('title', 'Getting Started')
                ->where('html', fn (string $html) => str_contains($html, '<a href="/Laravel" class="wikilink"')
                    && str_contains($html, 'class="wikilink wikilink-new"'))
                ->where('urls.edit', '/Getting_Started/-/edit')
                ->where('urls.history', '/Getting_Started/-/history'),
            );
    }

    public function test_different_case_redirects_to_canonical_title()
    {
        $this->createArticle('Laravel');

        $this->get('/laravel')->assertRedirect('/Laravel');
        $this->get('/laravel/-/history')->assertRedirect('/Laravel/-/history');
    }

    public function test_url_with_spaces_redirects_to_underscored_url()
    {
        $this->createArticle('Getting Started');

        $this->get('/Getting%20Started')->assertRedirect('/Getting_Started');
    }

    public function test_old_title_redirects_to_renamed_page()
    {
        $this->createArticle('Old');
        $article = $this->service->resolve('Old')['article'];
        $this->service->update($article, ['title' => 'New', 'body' => '本文'], $this->user, 1);

        $this->get('/Old')
            ->assertRedirect('/New')
            ->assertInertiaFlash('redirectedFrom', 'Old');
    }

    public function test_missing_page_shows_create_prompt_instead_of_404()
    {
        $this->get('/'.rawurlencode('未作成のページ'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wiki/Show')
                ->where('title', '未作成のページ')
                ->where('article', null)
                ->where('canCreate', false),
            );

        $this->actingAs($this->user)
            ->get('/'.rawurlencode('未作成のページ'))
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', true));
    }

    public function test_titles_that_cannot_be_created_are_404()
    {
        $this->get('/a%7Cb')->assertNotFound();      // |
        $this->get('/a%5Bb%5D')->assertNotFound();   // [ ]
        $this->get('/-')->assertNotFound();
        $this->get('/.env')->assertNotFound();
        $this->get('/test.php')->assertNotFound();
        $this->get('/home')->assertNotFound();
        $this->get('/'.str_repeat('a', 256))->assertNotFound();
    }

    public function test_titles_with_percent_are_encoded()
    {
        $this->createArticle('100%41');

        $this->get('/-/all-pages')
            ->assertInertia(fn (Assert $page) => $page->where('pages.0.url', '/100%2541'));

        $this->get('/100%2541')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('title', '100%41'));
    }

    public function test_system_pages_are_not_treated_as_titles()
    {
        $this->get('/-/all-pages')->assertInertia(fn (Assert $page) => $page->component('wiki/AllPages'));
        $this->get('/-/unknown')->assertNotFound();
    }

    public function test_system_screens_live_under_hyphen_prefix()
    {
        $this->assertSame('/-/login', route('login', absolute: false));
        $this->assertSame('/-/settings/profile', route('profile.edit', absolute: false));
        $this->get('/-/up')->assertOk();
    }

    public function test_pages_can_use_titles_of_former_system_urls()
    {
        foreach (['login', 'register', 'settings', 'dashboard', 'up'] as $title) {
            $this->createArticle($title);

            $this->get("/{$title}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('wiki/Show')
                    ->where('title', $title),
                );
        }
    }

    public function test_missing_page_history_is_404()
    {
        $this->get('/'.rawurlencode('未作成').'/-/history')->assertNotFound();
    }

    public function test_deleted_page_is_shown_as_missing()
    {
        $this->createArticle('Laravel');
        $this->service->resolve('Laravel')['article']->delete();

        $this->get('/Laravel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('article', null));
    }
}
