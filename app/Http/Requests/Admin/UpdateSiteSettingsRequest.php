<?php

namespace App\Http\Requests\Admin;

use App\Enums\ThemeColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:100'],
            'registration_enabled' => ['required', 'boolean'],
            'theme_color' => ['required', Rule::enum(ThemeColor::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'site_name' => 'Wiki 名',
            'registration_enabled' => 'アカウント登録',
            'theme_color' => 'テーマ色',
        ];
    }
}
