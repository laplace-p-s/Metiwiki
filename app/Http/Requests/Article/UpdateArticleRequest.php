<?php

namespace App\Http\Requests\Article;

use App\Concerns\ArticleValidationRules;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class UpdateArticleRequest extends FormRequest
{
    use ArticleValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string|In>>
     */
    public function rules(): array
    {
        /** @var Article $article */
        $article = $this->route('article');

        $titleRules = $this->titleRules($article);

        // ホームページはタイトルを変更できない（大文字小文字などの表記も固定）
        if (app(ArticleService::class)->isHome($article)) {
            $titleRules[] = Rule::in([ArticleService::HOME_TITLE]);
        }

        // ヘルプは改名できない。通常のページの名前空間に属さないので、同名ページとの重複も調べない
        if ($article->isHelp()) {
            $titleRules = ['required', 'string', Rule::in([ArticleService::HELP_TITLE])];
        }

        return [
            'title' => $titleRules,
            'body' => $this->bodyRules(),
            'summary' => $this->summaryRules(),
            // 編集を始めた時点の版番号（楽観ロック）
            'version_number' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.in' => 'このページのタイトルは変更できません。',
        ];
    }
}
