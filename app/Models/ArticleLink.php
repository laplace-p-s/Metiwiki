<?php

namespace App\Models;

use Database\Factories\ArticleLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $from_article_id
 * @property string $to_title_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['from_article_id', 'to_title_key'])]
class ArticleLink extends Model
{
    /** @use HasFactory<ArticleLinkFactory> */
    use HasFactory;

    /** @return BelongsTo<Article, $this> */
    public function fromArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'from_article_id');
    }
}
