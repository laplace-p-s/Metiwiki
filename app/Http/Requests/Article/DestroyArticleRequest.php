<?php

namespace App\Http\Requests\Article;

use App\Concerns\ArticleValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DestroyArticleRequest extends FormRequest
{
    use ArticleValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // 削除の理由（任意。削除の版の要約になる）
            'summary' => $this->summaryRules(),
        ];
    }
}
