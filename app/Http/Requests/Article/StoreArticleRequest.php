<?php

namespace App\Http\Requests\Article;

use App\Concerns\ArticleValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreArticleRequest extends FormRequest
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
            'title' => $this->titleRules(),
            'body' => $this->bodyRules(),
            'summary' => $this->summaryRules(),
        ];
    }
}
