<?php

namespace App\Concerns;

use App\Models\Article;
use App\Rules\ArticleTitle;
use App\Rules\AvailableArticleTitle;
use App\Rules\MaxBytes;
use App\Services\ArticleService;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ページの作成・更新で共通のバリデーション。FormRequest で使う。
 */
trait ArticleValidationRules
{
    /** タイトルは正規化してから検証する（保存値・照合キーと揃えるため） */
    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge([
                'title' => app(ArticleService::class)->normalizeTitle((string) $this->input('title')),
            ]);
        }
    }

    /**
     * 検証済みのページ内容
     *
     * @return array{title: string, body: string, summary: ?string}
     */
    public function articleData(): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'body' => $this->string('body')->toString(),
            'summary' => $this->filled('summary') ? $this->string('summary')->toString() : null,
        ];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function titleRules(?Article $ignore = null): array
    {
        return ['required', 'string', 'max:255', new ArticleTitle, new AvailableArticleTitle($ignore)];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function bodyRules(): array
    {
        return ['required', 'string', new MaxBytes(ArticleService::MAX_BODY_BYTES)];
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function summaryRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }
}
