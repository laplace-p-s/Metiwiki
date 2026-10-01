<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ArticleTitleTakenException;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleService;
use App\Support\ArticleUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 削除済みページの一覧と復元（管理者のみ）。ページの削除はソフトデリートなので、本文と履歴は残っている。
 * 削除と復元はそれぞれ版として履歴に記録する
 */
class DeletedArticleController extends Controller
{
    public function __construct(private ArticleService $service) {}

    public function index(): Response
    {
        $articles = Article::onlyTrashed()
            ->select(['id', 'title', 'title_key', 'updated_by', 'updated_at', 'deleted_by', 'deleted_at'])
            ->with([
                'updater:id,name',
                'deleter:id,name',
                // 本文は読まない（最大 1MB）
                'latestDeletion' => fn ($query) => $query->select([
                    'article_versions.id', 'article_versions.article_id', 'article_versions.summary',
                ]),
            ])
            ->withCount('versions')
            ->latest('deleted_at')
            ->latest('id')
            ->paginate(30);

        // 削除後に同じタイトルで作り直されたページ（復元できない）
        $taken = Article::query()
            ->whereIn('title_key', $articles->getCollection()->pluck('title_key')->unique())
            ->pluck('title', 'title_key');

        return Inertia::render('admin/DeletedPages', [
            'articles' => $articles->through(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'deleted_at' => $article->deleted_at?->toIso8601String(),
                'deleter' => $article->deleter?->name,
                'reason' => $article->latestDeletion?->summary,
                'updated_at' => $article->updated_at?->toIso8601String(),
                'updater' => $article->updater?->name,
                'versionCount' => $article->versions_count,
                'takenBy' => isset($taken[$article->title_key]) ? [
                    'title' => $taken[$article->title_key],
                    'url' => ArticleUrl::show($taken[$article->title_key]),
                ] : null,
            ]),
        ]);
    }

    public function restore(Request $request, int $article): RedirectResponse
    {
        $article = Article::onlyTrashed()->findOrFail($article);

        Gate::authorize('restore', $article);

        try {
            $this->service->restoreDeleted($article, $request->user());
        } catch (ArticleTitleTakenException $e) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "「{$e->existing->title}」が既にあるため復元できません。先にそのページを改名するか削除してください。",
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$article->title}」を復元しました。"]);

        return redirect(ArticleUrl::show($article->title));
    }
}
