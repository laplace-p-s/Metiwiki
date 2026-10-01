<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * パスワードの既定の規則（Password::defaults()）を、入力欄に添える説明文にする。
 * 規則は環境で変わる（本番は 12 文字以上で英大小文字・数字・記号が必要、それ以外は 8 文字以上）。
 */
class PasswordRequirements
{
    public static function describe(?Password $rule = null): string
    {
        $applied = ($rule ?? Password::defaults())->appliedRules();

        $parts = ["{$applied['min']}文字以上"];

        if ($applied['max'] !== null) {
            $parts[] = "{$applied['max']}文字以下";
        }

        $required = array_keys(array_filter([
            '英大文字と小文字' => $applied['mixedCase'],
            '英字' => $applied['letters'] && ! $applied['mixedCase'],
            '数字' => $applied['numbers'],
            '記号' => $applied['symbols'],
        ]));

        if ($required !== []) {
            $parts[] = implode('・', $required).'を含む';
        }

        return implode('、', $parts);
    }
}
