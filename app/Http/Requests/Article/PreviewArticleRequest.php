<?php

namespace App\Http\Requests\Article;

use App\Rules\MaxBytes;
use App\Services\ArticleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewArticleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // 書きかけの空の本文もプレビューできるようにする
            'body' => ['present', 'nullable', 'string', new MaxBytes(ArticleService::MAX_BODY_BYTES)],
        ];
    }
}
