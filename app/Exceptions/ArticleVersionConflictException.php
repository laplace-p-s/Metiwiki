<?php

namespace App\Exceptions;

use App\Models\Article;
use RuntimeException;

/**
 * 楽観ロックの競合。編集を始めた時点の版より新しい版が、先に保存されていた。
 */
class ArticleVersionConflictException extends RuntimeException
{
    public function __construct(
        public readonly Article $article,
        public readonly int $latestVersion,
    ) {
        parent::__construct("Article {$article->id} has been updated to version {$latestVersion}.");
    }
}
