<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_pages_require_admin()
    {
        $editor = User::factory()->create();

        foreach (['/-/admin/users', '/-/admin/users/create', '/-/admin/invitations', '/-/admin/settings'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
            $this->actingAs($editor)->get($url)->assertForbidden();
            auth()->logout();
        }

        $this->actingAs($editor)
            ->post(route('admin.users.store'), [
                'name' => '侵入者', 'login_id' => 'intruder', 'password' => 'password', 'password_confirmation' => 'password', 'is_admin' => true,
            ])
            ->assertForbidden();

        $this->assertSame(0, User::where('login_id', 'intruder')->count());
    }

    public function test_admin_top_redirects_to_users()
    {
        $this->actingAs($this->admin)->get('/-/admin')->assertRedirect('/-/admin/users');
    }

    public function test_users_are_listed_without_secrets()
    {
        User::factory()->withTwoFactor()->create(['login_id' => 'alice']);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->where('users.total', 2)
                ->has('users.data.0', fn (Assert $user) => $user
                    ->hasAll(['id', 'name', 'login_id', 'email', 'is_admin', 'two_factor_enabled', 'created_at', 'is_self'])),
            );
    }

    public function test_admin_can_create_user()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.store'), [
                'name' => '新人',
                'login_id' => 'Newbie',
                'email' => '',
                'password' => 'password',
                'password_confirmation' => 'password',
                'is_admin' => false,
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::where('login_id', 'newbie')->sole();
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->email);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_admin_can_create_another_admin()
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => '副管理者', 'login_id' => 'sub', 'password' => 'password', 'password_confirmation' => 'password', 'is_admin' => true,
        ]);

        $this->assertTrue(User::where('login_id', 'sub')->sole()->is_admin);
    }

    public function test_admin_can_update_user_and_grant_admin()
    {
        $user = User::factory()->create(['login_id' => 'alice']);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $user), [
                'name' => 'アリス',
                'login_id' => 'Alice2',
                'email' => 'alice@example.com',
                'is_admin' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.users.edit', $user));

        $user->refresh();
        $this->assertSame('アリス', $user->name);
        $this->assertSame('alice2', $user->login_id);
        $this->assertTrue($user->is_admin);
    }

    public function test_last_admin_cannot_be_demoted()
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->admin))
            ->assertInertia(fn (Assert $page) => $page->where('isLastAdmin', true)->where('isSelf', true));

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name,
                'login_id' => $this->admin->login_id,
                'is_admin' => false,
            ])
            ->assertSessionHasErrors('is_admin');

        $this->assertTrue($this->admin->fresh()->is_admin);
    }

    public function test_admin_can_be_demoted_when_another_admin_exists()
    {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->patch(route('admin.users.update', $other), [
                'name' => $other->name,
                'login_id' => $other->login_id,
                'is_admin' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($other->fresh()->is_admin);
    }

    public function test_admin_can_reset_password()
    {
        $user = User::factory()->create();
        $oldToken = $user->remember_token;

        $this->actingAs($this->admin)
            ->put(route('admin.users.password', $user), [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame($oldToken, $user->remember_token);

        auth()->logout();
        $this->post(route('login.store'), ['login_id' => $user->login_id, 'password' => 'new-password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_disable_two_factor()
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.users.two-factor', $user))
            ->assertRedirect(route('admin.users.edit', $user));

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        auth()->logout();
        $this->post(route('login.store'), ['login_id' => $user->login_id, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_delete_user_and_pages_remain()
    {
        $user = User::factory()->create();
        $article = app(ArticleService::class)->create(['title' => 'Laravel', 'body' => '本文'], $user);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull($user->fresh());
        $this->assertNull($article->fresh()->created_by);
        $this->assertSame(1, Article::count());
    }

    public function test_admin_cannot_delete_self()
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHasErrors('user');

        $this->assertNotNull($this->admin->fresh());
    }

    public function test_last_admin_cannot_delete_own_account_from_profile()
    {
        $this->actingAs($this->admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($this->admin->fresh());
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_admin_can_delete_own_account_when_another_admin_exists()
    {
        User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertNull($this->admin->fresh());
    }

    public function test_disable_two_factor_command()
    {
        $user = User::factory()->withTwoFactor()->create(['login_id' => 'alice']);

        $this->artisan('wiki:disable-2fa', ['login_id' => 'ALICE'])->assertSuccessful();
        $this->assertNull($user->fresh()->two_factor_confirmed_at);

        $this->artisan('wiki:disable-2fa', ['login_id' => 'nobody'])->assertFailed();
    }

    public function test_reset_password_command()
    {
        $user = User::factory()->create(['login_id' => 'alice']);

        $this->artisan('wiki:reset-password', ['login_id' => 'alice', '--password' => 'new-password'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));

        $this->artisan('wiki:reset-password', ['login_id' => 'nobody', '--password' => 'x'])->assertFailed();
    }
}
