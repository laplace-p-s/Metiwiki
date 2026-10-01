<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.login'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ]);

        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/TwoFactorChallenge'),
            );
    }

    public function test_user_can_log_in_with_login_id_and_totp_code(): void
    {
        $user = User::factory()->withTwoFactor()->create(['login_id' => 'alice']);

        $this->post(route('login.store'), [
            'login_id' => 'ALICE',
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();

        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret)),
        ])->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_returns_to_original_page_after_two_factor_challenge(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->get(route('login', ['redirect' => '/Laravel']));

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret)),
        ])->assertRedirect(url('/Laravel'));
    }

    public function test_user_can_log_in_with_login_id_and_recovery_code(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'recovery-code-1',
        ])->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_two_factor_code_is_rejected(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ]);

        $this->post(route('two-factor.login.store'), [
            'code' => '000000',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_two_factor_challenge_is_rate_limited(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->post(route('login.store'), [
            'login_id' => $user->login_id,
            'password' => 'password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('two-factor.login.store'), ['code' => '000000'])
                ->assertRedirect(route('two-factor.login'));
        }

        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret)),
        ])->assertTooManyRequests();

        $this->assertGuest();
    }
}
