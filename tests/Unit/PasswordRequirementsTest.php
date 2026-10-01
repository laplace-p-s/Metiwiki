<?php

namespace Tests\Unit;

use App\Support\PasswordRequirements;
use Illuminate\Validation\Rules\Password;
use PHPUnit\Framework\TestCase;

class PasswordRequirementsTest extends TestCase
{
    public function test_minimum_length_only()
    {
        $this->assertSame('8文字以上', PasswordRequirements::describe(Password::min(8)));
    }

    public function test_production_rule()
    {
        $rule = Password::min(12)->mixedCase()->letters()->numbers()->symbols();

        $this->assertSame('12文字以上、英大文字と小文字・数字・記号を含む', PasswordRequirements::describe($rule));
    }

    public function test_letters_and_maximum_length()
    {
        $rule = Password::min(10)->max(64)->letters();

        $this->assertSame('10文字以上、64文字以下、英字を含む', PasswordRequirements::describe($rule));
    }
}
