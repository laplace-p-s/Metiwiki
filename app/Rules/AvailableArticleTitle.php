<?php

namespace App\Rules;

use App\Models\Article;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 同じ照合キーのページ（削除済みを除く）が無いか。正規化済みの値を検証する。
 *
 * DB の照合順序に依存しないよう、unique ルールではなく照合キーの完全一致で判定する。
 */
class AvailableArticleTitle implements ValidationRule
{
    public function __construct(private ?Article $ignore = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $exists = Article::query()
            ->where('title_key', Article::titleKey($value))
            ->when($this->ignore, fn ($q) => $q->whereKeyNot($this->ignore->id))
            ->exists();

        if ($exists) {
            $fail('このタイトルのページはすでに存在します。');
        }
    }
}
