<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesLoginId;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupRequest extends FormRequest
{
    use NormalizesLoginId, PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Password|ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:100'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['site_name' => 'Wiki 名'];
    }

    /**
     * sample_pages はチェックボックス（チェック時は "on"、外すと送られない）
     *
     * @return array{site_name: string, name: string, login_id: string, email: ?string, password: string, sample_pages: bool}
     */
    public function setupData(): array
    {
        return [
            'site_name' => $this->string('site_name')->trim()->toString(),
            ...$this->profileData(),
            'password' => $this->string('password')->toString(),
            'sample_pages' => $this->boolean('sample_pages'),
        ];
    }
}
