<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * login_id は小文字化してから検証すること（normalizeLoginId()）。
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'login_id' => $this->loginIdRules($userId),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * ログイン ID のルール。半角英数字・ハイフン・アンダースコアのみ。
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function loginIdRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'alpha_dash:ascii',
            'max:64',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * メールアドレスは任意（パスワードリセットのメール送信にだけ使う）。
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'nullable',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * ログイン ID を保存時と同じく小文字化する（一意性の検証を保存値と揃えるため）。
     */
    protected function normalizeLoginId(mixed $loginId): mixed
    {
        return is_string($loginId) ? mb_strtolower($loginId) : $loginId;
    }
}
