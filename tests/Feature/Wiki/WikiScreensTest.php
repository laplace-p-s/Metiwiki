<?php

namespace Tests\Feature\Wiki;

use App\Models\Setting;
use App\Models\User;
use App\Services\ArticleService;
use App\Support\AuthFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 画面（D）で追加したサーバー側の動作：新規作成の入口・ヘルプ・共有 props・日本語化
 */
class WikiScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_page_entry_redirects_to_normalized_edit_url()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('wiki.create', ['title' => '　Getting_Started ']))
            ->assertRedirect('/Getting_Started/-/edit');
    }

    public function test_new_page_entry_redirects_to_existing_page_with_canonical_title()
    {
        $user = User::factory()->create();
        app(ArticleService::class)->create(['title' => 'Laravel', 'body' => '本文'], $user);

        $this->actingAs($user)
            ->get(route('wiki.create', ['title' => 'laravel']))
            ->assertRedirect('/Laravel/-/edit');
    }

    public function test_new_page_entry_rejects_invalid_title()
    {
        $this->actingAs(User::factory()->create())
            ->from('/')
            ->get(route('wiki.create', ['title' => 'a/b']))
            ->assertRedirect('/')
            ->assertSessionHasErrors('title');
    }

    public function test_new_page_entry_requires_login()
    {
        $this->get(route('wiki.create', ['title' => '新規']))->assertRedirect(route('login'));
    }

    public function test_edit_screen_has_tab_urls()
    {
        $user = User::factory()->create();
        app(ArticleService::class)->create(['title' => 'Laravel', 'body' => '本文'], $user);

        $this->actingAs($user)
            ->get('/Laravel/-/edit')
            ->assertInertia(fn (Assert $page) => $page
                ->where('urls.show', '/Laravel')
                ->where('urls.edit', '/Laravel/-/edit')
                ->where('urls.history', '/Laravel/-/history'),
            );
    }

    public function test_history_tabs_hide_edit_for_guests()
    {
        $user = User::factory()->create();
        app(ArticleService::class)->create(['title' => 'Laravel', 'body' => '本文'], $user);

        $this->get('/Laravel/-/history')
            ->assertInertia(fn (Assert $page) => $page
                ->where('urls.view', '/Laravel')
                ->where('urls.edit', null)
                ->where('urls.diff', '/Laravel/-/diff'),
            );

        $this->actingAs($user)
            ->get('/Laravel/-/history')
            ->assertInertia(fn (Assert $page) => $page->where('urls.edit', '/Laravel/-/edit'));
    }

    public function test_shared_user_props_are_limited()
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('auth.user', fn (Assert $shared) => $shared
                    ->where('id', $user->id)
                    ->where('name', $user->name)
                    ->where('login_id', $user->login_id)
                    ->where('email', $user->email)
                    ->where('is_admin', false),
                )
                ->where('canRegister', false),
            );
    }

    public function test_guests_get_registration_availability()
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('auth.user', null)
            ->where('canRegister', false));

        Setting::create(['key' => AuthFeatures::REGISTRATION_SETTING, 'value' => '1']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
    }

    public function test_app_version_is_shared_only_with_admins()
    {
        config(['app.version' => '9.8.7']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('appVersion', null));

        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('appVersion', null));

        $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('appVersion', '9.8.7'));
    }

    public function test_messages_are_in_japanese()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('wiki.store'), ['title' => '', 'body' => ''])
            ->assertSessionHasErrors([
                'title' => 'タイトルは必須項目です。',
                'body' => '本文は必須項目です。',
            ]);

        auth()->logout();

        $this->post(route('login.store'), ['login_id' => 'nobody', 'password' => 'wrong'])
            ->assertSessionHasErrors(['login_id' => 'ログインIDまたはパスワードが正しくありません。']);
    }

    public function test_starter_kit_pages_are_removed()
    {
        $this->assertFalse(app('router')->has('dashboard'));
        $this->assertFileDoesNotExist(resource_path('js/pages/Welcome.vue'));
        $this->assertFileDoesNotExist(resource_path('js/pages/Dashboard.vue'));
    }
}
