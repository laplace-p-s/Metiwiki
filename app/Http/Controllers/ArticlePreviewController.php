<?php

namespace App\Http\Controllers;

use App\Http\Requests\Article\PreviewArticleRequest;
use App\Models\Article;
use App\Services\ArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * 編集プレビュー。閲覧ページと同じサーバー描画の結果を返す。
 */
class ArticlePreviewController extends Controller
{
    public function __invoke(PreviewArticleRequest $request, ArticleService $service): JsonResponse
    {
        Gate::authorize('create', Article::class);

        return response()->json($service->render($request->string('body')->toString()));
    }
}
