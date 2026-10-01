<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wiki ページの検索。
 *
 * 日本語には分かち書きがなく FULLTEXT（トークン境界前提）が馴染まないため、
 * 空白区切りの各語を部分一致（LIKE）で AND 検索する（語をまたいで AND、語の中では
 * タイトル・本文の OR）。ヒット箇所が必ず本文中の実文字列になるためスニペットで提示できる。
 */
class ArticleSearchService
{
    /** 最大表示件数 */
    public const LIMIT = 50;

    /** AND 条件に使う検索語の最大数（過剰な条件を防ぐ） */
    private const MAX_TERMS = 10;

    /**
     * @return list<array{id: int, title: string, snippet: string, updated_at: ?string}>
     */
    public function search(string $query): array
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return [];
        }

        $builder = Article::query();

        foreach ($terms as $term) {
            $builder->where(fn (Builder $w) => $this->whereTitleOrBodyContains($w, $term));
        }

        return array_values($builder
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get(['id', 'title', 'body', 'updated_at'])
            ->map(fn (Article $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'snippet' => $this->snippet($a->body, $terms),
                'updated_at' => $a->updated_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * 検索文字列を語に分解する。半角・全角スペースの両方で区切り、
     * 重複を除いて最大 MAX_TERMS 語までに制限する。
     *
     * @return list<string>
     */
    public function terms(string $query): array
    {
        $parts = preg_split('/[\s\x{3000}]+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique($parts)), 0, self::MAX_TERMS);
    }

    /**
     * タイトルか本文に語を含む条件。英字の大文字小文字だけを無視し、カナ・濁点・半角全角は区別する。
     *
     * @param  Builder<Article>  $query
     */
    private function whereTitleOrBodyContains(Builder $query, string $term): void
    {
        if (in_array($query->getModel()->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // 既定の utf8mb4_unicode_ci はひらがな・カタカナ・濁点差まで同一視し、「てす」で
            // 「テスト」が誤ヒットする。両辺を小文字化したうえでバイナリ比較する（エスケープ文字は既定の \）
            $like = '%'.addcslashes(mb_strtolower($term), '\\%_').'%';
            $query->whereRaw('LOWER(title) COLLATE utf8mb4_bin LIKE ?', [$like])
                ->orWhereRaw('LOWER(body) COLLATE utf8mb4_bin LIKE ?', [$like]);

            return;
        }

        // SQLite の LIKE は ASCII の大文字小文字だけを無視し、それ以外は区別する。
        // エスケープ文字の既定が無いため ESCAPE を明示する
        $like = '%'.addcslashes($term, '\\%_').'%';
        $query->whereRaw("title LIKE ? ESCAPE '\\'", [$like])
            ->orWhereRaw("body LIKE ? ESCAPE '\\'", [$like]);
    }

    /**
     * 本文から、最初にヒットした検索語の周辺を抜き出したスニペットを作る。
     * ヒット語が本文になければ（タイトル一致のみ等）先頭から抜粋する。
     *
     * @param  list<string>  $terms
     */
    private function snippet(string $body, array $terms): string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? '');
        if ($plain === '') {
            return '';
        }

        // 本文中で最初に現れる検索語の位置を探す
        $pos = false;
        foreach ($terms as $term) {
            $found = mb_stripos($plain, $term);
            if ($found !== false && ($pos === false || $found < $pos)) {
                $pos = $found;
            }
        }

        if ($pos === false) {
            return mb_substr($plain, 0, 120).(mb_strlen($plain) > 120 ? '…' : '');
        }

        $start = max(0, $pos - 40);
        $snippet = mb_substr($plain, $start, 160);

        return ($start > 0 ? '…' : '').$snippet.(mb_strlen($plain) > $start + 160 ? '…' : '');
    }
}
