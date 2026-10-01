<?php

namespace App\Exceptions;

use App\Models\Article;
use RuntimeException;

/**
 * 削除済みページを復元しようとしたが、同じタイトル（照合キー）のページが既に存在する。
 */
class ArticleTitleTakenException extends RuntimeException
{
    public function __construct(
        public readonly Article $article,
        public readonly Article $existing,
    ) {
        parent::__construct("Article {$article->id} cannot be restored: title is used by article {$existing->id}.");
    }
}
