<?php

namespace Tests\Unit;

use App\Rules\MaxBytes;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MaxBytesTest extends TestCase
{
    public function test_value_within_limit_passes()
    {
        $validator = Validator::make(['body' => str_repeat('a', 10)], ['body' => [new MaxBytes(10)]]);

        $this->assertTrue($validator->passes());
    }

    public function test_limit_is_counted_in_bytes_not_characters()
    {
        // 「あ」は UTF-8 で 3 バイトなので、4 文字で 12 バイトになり 10 バイトを超える
        $validator = Validator::make(['body' => 'ああああ'], ['body' => [new MaxBytes(10)]]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('10バイト以内', $validator->errors()->first('body'));
        $this->assertStringContainsString('現在12バイト', $validator->errors()->first('body'));
    }

    public function test_default_limit_is_text_column_size()
    {
        $passes = Validator::make(['body' => str_repeat('a', 65535)], ['body' => [new MaxBytes]]);
        $fails = Validator::make(['body' => str_repeat('a', 65536)], ['body' => [new MaxBytes]]);

        $this->assertTrue($passes->passes());
        $this->assertTrue($fails->fails());
    }

    public function test_non_string_value_is_ignored()
    {
        $validator = Validator::make(['body' => ['a', 'b']], ['body' => [new MaxBytes(1)]]);

        $this->assertTrue($validator->passes());
    }
}
