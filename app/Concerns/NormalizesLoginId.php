<?php

namespace App\Concerns;

/**
 * FormRequest でログイン ID を検証前に小文字化する（保存値と一意性の検証を揃えるため）。
 * ProfileValidationRules と一緒に使う。
 */
trait NormalizesLoginId
{
    protected function prepareForValidation(): void
    {
        if ($this->has('login_id')) {
            $this->merge(['login_id' => $this->normalizeLoginId($this->input('login_id'))]);
        }
    }

    /**
     * 検証済みのプロフィール項目
     *
     * @return array{name: string, login_id: string, email: ?string}
     */
    public function profileData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'login_id' => $this->string('login_id')->toString(),
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
        ];
    }
}
