<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * バイト数ベースの上限チェック。
 *
 * MySQL の TEXT カラムは 65,535「バイト」が上限であり、Laravel の max ルール（文字数ベース）では
 * 多バイト文字の入力を通してしまい strict モードの DB エラー（500）になる。
 * TEXT カラムに保存する本文系フィールドにはこのルールを使う。
 */
class MaxBytes implements ValidationRule
{
    public function __construct(private int $maxBytes = 65535) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $bytes = strlen($value);

        if ($bytes > $this->maxBytes) {
            $fail(sprintf(
                ':attributeは%sバイト以内で入力してください（現在%sバイト。日本語等の多バイト文字は1文字で2〜4バイトになります）。',
                number_format($this->maxBytes),
                number_format($bytes)
            ));
        }
    }
}
