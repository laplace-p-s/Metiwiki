<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleService;

/**
 * 閲覧はゲストを含む誰でも、編集（作成・更新・改名・削除・版の復元）はログインユーザー、
 * 削除済みページの復元は管理者だけに許可する。
 * ヘルプ（システムページ）は、編集・版の復元・履歴の閲覧が管理者だけで、削除できない。
 */
class ArticlePolicy
{
    public function __construct(private ArticleService $service) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Article $article): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** 履歴・版・差分の閲覧。ヘルプは管理者だけ */
    public function viewHistory(?User $user, Article $article): bool
    {
        return ! $article->isHelp() || $user?->is_admin === true;
    }

    public function update(User $user, Article $article): bool
    {
        // ヘルプは管理者だけが編集できる
        return ! $article->isHelp() || $user->is_admin;
    }

    public function delete(User $user, Article $article): bool
    {
        // ホームページとシステムページ（ヘルプ）は削除できない
        return ! $this->service->isHome($article) && ! $article->isSystem();
    }

    public function restore(User $user, Article $article): bool
    {
        return $user->is_admin;
    }
}
