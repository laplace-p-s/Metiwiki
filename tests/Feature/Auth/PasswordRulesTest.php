<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class PasswordRulesTest extends TestCase
{
    private function passes(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => Password::default()])->passes();
    }

    public function test_production_requires_strong_password_without_external_requests()
    {
        $this->app['env'] = 'production';
        Http::fake();
        Http::preventStrayRequests();

        $this->assertTrue($this->passes('Metiwiki-2026!'));
        $this->assertFalse($this->passes('short-A1!'));
        $this->assertFalse($this->passes('metiwiki-2026!'));
        $this->assertFalse($this->passes('Metiwiki-abcd!'));
        $this->assertFalse($this->passes('Metiwiki20266'));

        // 流出パスワードの照合（Have I Been Pwned への問い合わせ）はしない
        Http::assertNothingSent();
    }

    public function test_non_production_has_no_extra_rules()
    {
        $this->assertTrue($this->passes('password'));
    }
}
