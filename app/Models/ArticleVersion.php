<?php

namespace App\Models;

use App\Enums\VersionKind;
use Database\Factories\ArticleVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $article_id
 * @property int|null $user_id
 * @property string $body
 * @property string|null $summary
 * @property int $version_number
 * @property VersionKind $kind
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['article_id', 'user_id', 'body', 'summary', 'version_number', 'kind'])]
class ArticleVersion extends Model
{
    /** @use HasFactory<ArticleVersionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'kind' => VersionKind::class,
        ];
    }

    /** @return BelongsTo<Article, $this> */
    public function article(): BelongsTo
    {
        // 削除済みページ・システムページの版も参照できるようにする
        return $this->belongsTo(Article::class)->withTrashed()->withoutGlobalScope(Article::CONTENT_SCOPE);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
