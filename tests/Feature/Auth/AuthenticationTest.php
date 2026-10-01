<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use App\Support\AuthFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));
    }

    public function test_users_return_to_the_page_where_they_pressed_login()
    {
        $user = User::factory()->create();

        $this->get(route('login', ['redirect' => '/Getting_Started?x=1']))->assertOk();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ])->assertRedirect(url('/Getting_Started?x=1'));
    }

    public function test_return_path_after_failed_login_attempt_is_kept()
    {
        $user = User::factory()->create();

        $this->get(route('login', ['redirect' => '/ページ']));

        $this->post(route('login.store'), ['login_id' => $user->login_id, 'password' => 'wrong']);
        $this->assertGuest();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ])->assertRedirect(url('/ページ'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function externalReturnPaths(): array
    {
        return [
            'absolute url' => ['https://evil.example/'],
            'protocol relative' => ['//evil.example/'],
            'backslash' => ['/\\evil.example/'],
            'relative' => ['evil'],
        ];
    }

    #[DataProvider('externalReturnPaths')]
    public function test_return_path_must_be_a_path_on_this_site(string $path)
    {
        $user = User::factory()->create();

        $this->get(route('login', ['redirect' => $path]))->assertOk();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ])->assertRedirect(route('home', absolute: false));
    }

    public function test_login_id_is_case_insensitive()
    {
        $user = User::factory()->create(['login_id' => 'Alice']);

        $this->assertSame('alice', $user->login_id);

        $this->post(route('login.store'), [
            'login_id' => 'ALICE',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_cannot_authenticate_with_email_address()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'login_id' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_login_screen_hides_links_for_disabled_features()
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canResetPassword', false)
                ->where('canRegister', false),
            );
    }

    public function test_login_screen_shows_links_for_enabled_features()
    {
        config(['mail.default' => 'smtp']);
        Setting::create(['key' => AuthFeatures::REGISTRATION_SETTING, 'value' => '1']);

        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Login')
                ->where('canResetPassword', true)
                ->where('canRegister', true),
            );
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->login_id, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
