<?php

namespace Tests\Feature\Auth;

use App\Models\Setting;
use App\Models\User;
use App\Support\AuthFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    private function enableRegistration(): void
    {
        Setting::create(['key' => AuthFeatures::REGISTRATION_SETTING, 'value' => '1']);
    }

    public function test_registration_is_disabled_by_default()
    {
        $this->get(route('register'))->assertNotFound();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'login_id' => 'test-user',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_registration_screen_can_be_rendered_when_enabled()
    {
        $this->enableRegistration();

        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register_without_email()
    {
        $this->enableRegistration();

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'login_id' => 'Test-User',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home', absolute: false));

        $user = User::sole();
        $this->assertSame('test-user', $user->login_id);
        $this->assertNull($user->email);
        $this->assertFalse($user->is_admin);
    }

    public function test_new_users_can_register_with_email()
    {
        $this->enableRegistration();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'login_id' => 'test-user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertSame('test@example.com', User::sole()->email);
    }

    public function test_login_id_must_be_unique_ignoring_case()
    {
        $this->enableRegistration();
        User::factory()->create(['login_id' => 'alice']);

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'login_id' => 'ALICE',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('login_id');

        $this->assertGuest();
    }

    public function test_login_id_must_consist_of_allowed_characters()
    {
        $this->enableRegistration();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'login_id' => 'test user',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('login_id');

        $this->assertGuest();
    }

    public function test_registration_is_rate_limited()
    {
        $this->enableRegistration();

        $attempt = fn () => $this->post(route('register.store'), [
            'name' => 'Test User',
            'login_id' => 'test user',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $attempt()->assertSessionHasErrors('login_id');
        }

        $attempt()->assertTooManyRequests();
    }

    public function test_registration_screen_is_not_rate_limited()
    {
        $this->enableRegistration();

        for ($i = 0; $i < 11; $i++) {
            $this->get(route('register'))->assertOk();
        }
    }
}
