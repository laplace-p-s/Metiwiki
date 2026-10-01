<?php

namespace Tests\Feature\Wiki;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\ArticleUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ゲスト／編集者（ログインユーザー）／管理者の権限差
 */
class ArticlePermissionTest extends TestCase
{
    use RefreshDatabase;

    private ArticleService $service;

    private Article $article;

    private Article $home;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ArticleService::class);
        $author = User::factory()->create();
        $this->home = $this->service->create(['title' => ArticleService::HOME_TITLE, 'body' => 'ホーム'], $author);
        $this->article = $this->service->create(['title' => 'Laravel', 'body' => 'v1'], $author);
        $this->service->update($this->article, ['title' => 'Laravel', 'body' => 'v2'], $author, 1);
    }

    /** @return array<string, array{?string}> */
    public static function roles(): array
    {
        return [
            'guest' => [null],
            'editor' => ['editor'],
            'admin' => ['admin'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function loggedInRoles(): array
    {
        return [
            'editor' => ['editor'],
            'admin' => ['admin'],
        ];
    }

    private function actAs(?string $role): void
    {
        match ($role) {
            'editor' => $this->actingAs(User::factory()->create()),
            'admin' => $this->actingAs(User::factory()->admin()->create()),
            default => null,
        };
    }

    #[DataProvider('roles')]
    public function test_everyone_can_read(?string $role)
    {
        $this->actAs($role);

        $this->get('/')->assertOk();
        $this->get('/Laravel')->assertOk();
        $this->get('/'.rawurlencode('未作成'))->assertOk();
        $this->get('/Laravel/-/history')->assertOk();
        $this->get('/Laravel/-/history/1')->assertOk();
        $this->get('/Laravel/-/diff')->assertOk();
        $this->get('/-/recent-changes')->assertOk();
        $this->get('/-/all-pages')->assertOk();
        $this->get('/-/search?q=Laravel')->assertOk();
    }

    public function test_guest_sees_no_edit_controls()
    {
        $this->get('/Laravel')
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', false)
                ->missing('canDelete'),
            );
        $this->get('/Laravel/-/history')
            ->assertInertia(fn (Assert $page) => $page->where('canRestore', false));
        $this->get('/'.rawurlencode('未作成'))
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
    }

    #[DataProvider('loggedInRoles')]
    public function test_logged_in_users_see_edit_controls(string $role)
    {
        $this->actAs($role);

        $this->get('/Laravel')
            ->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
        // 削除ボタンは閲覧ではなく編集タブに出す
        $this->get('/Laravel/-/edit')
            ->assertInertia(fn (Assert $page) => $page->where('canDelete', true));
        $this->get('/'.rawurlencode('未作成').'/-/edit')
            ->assertInertia(fn (Assert $page) => $page->where('canDelete', false));
        $this->get('/Laravel/-/history')
            ->assertInertia(fn (Assert $page) => $page->where('canRestore', true));
        $this->get('/'.rawurlencode('未作成'))
            ->assertInertia(fn (Assert $page) => $page->where('canCreate', true));
    }

    public function test_guest_is_sent_to_login_for_editing()
    {
        $login = route('login');

        $this->get('/Laravel/-/edit')->assertRedirect($login);
        $this->get('/'.rawurlencode('未作成').'/-/edit')->assertRedirect($login);
        $this->post(route('wiki.store'), ['title' => '新規', 'body' => '本文'])->assertRedirect($login);
        $this->patch(route('wiki.update', $this->article), [
            'title' => 'Laravel', 'body' => '改ざん', 'version_number' => 2,
        ])->assertRedirect($login);
        $this->delete(route('wiki.destroy', $this->article))->assertRedirect($login);
        $this->post(route('wiki.restore-version', [$this->article, 1]))->assertRedirect($login);
        $this->postJson(route('wiki.preview'), ['body' => '本文'])->assertUnauthorized();

        $this->assertSame('v2', $this->article->fresh()->body);
        $this->assertNotSoftDeleted($this->article);
        $this->assertSame(2, Article::count());
    }

    #[DataProvider('loggedInRoles')]
    public function test_logged_in_users_can_edit(string $role)
    {
        $this->actAs($role);

        $this->get('/Laravel/-/edit')->assertOk();
        $this->post(route('wiki.store'), ['title' => '新規', 'body' => '本文'])->assertRedirect('/'.rawurlencode('新規'));
        $this->patch(route('wiki.update', $this->article), [
            'title' => 'Laravel', 'body' => 'v3', 'version_number' => 2,
        ])->assertRedirect('/Laravel');
        $this->post(route('wiki.restore-version', [$this->article, 1]))->assertRedirect('/Laravel');
        $this->postJson(route('wiki.preview'), ['body' => '本文'])->assertOk();
        $this->delete(route('wiki.destroy', $this->article))->assertRedirect('/');

        $this->assertSoftDeleted($this->article);
    }

    #[DataProvider('loggedInRoles')]
    public function test_nobody_can_delete_home_page(string $role)
    {
        $this->actAs($role);

        $this->delete(route('wiki.destroy', $this->home))->assertForbidden();
        $this->get(ArticleUrl::edit($this->home->title))
            ->assertInertia(fn (Assert $page) => $page->where('canDelete', false));

        $this->assertNotSoftDeleted($this->home);
    }

    public function test_only_admin_can_restore_deleted_pages()
    {
        $this->article->delete();

        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('restore', $this->article));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('restore', $this->article));
        $this->assertFalse(Gate::allows('restore', $this->article));
    }

    public function test_public_props_do_not_expose_private_user_fields()
    {
        $this->get('/Laravel')
            ->assertInertia(fn (Assert $page) => $page
                ->has('article.creator', fn (Assert $user) => $user->hasAll(['id', 'name']))
                ->has('article.updater', fn (Assert $user) => $user->hasAll(['id', 'name'])),
            );

        $this->get('/-/recent-changes')
            ->assertInertia(fn (Assert $page) => $page
                ->has('versions.data.0.user', fn (Assert $user) => $user->hasAll(['id', 'name'])),
            );
    }
}
