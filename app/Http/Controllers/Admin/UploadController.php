<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Upload;
use App\Services\UploadService;
use App\Support\ArticleUrl;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者向けの画像一覧。画像は自動では削除しないため、使われていないものを管理者が手動で削除する（決定 17）
 */
class UploadController extends Controller
{
    public function __construct(private UploadService $uploads) {}

    public function index(): Response
    {
        $uploads = Upload::query()
            ->with('user:id,name')
            ->latest('id')
            ->paginate(30)
            ->through(function (Upload $upload) {
                $pages = $this->uploads->pagesUsing($upload);

                return [
                    'id' => $upload->id,
                    'name' => $upload->original_name,
                    'url' => $upload->url(),
                    'mime' => $upload->mime,
                    'size' => $upload->size,
                    'created_at' => $upload->created_at?->toIso8601String(),
                    'user' => $upload->user?->name,
                    'usageCount' => $pages->count(),
                    // 使っているページ（多すぎると一覧が長くなるので先頭の数件だけ）
                    'usedIn' => $pages->take(5)->map(fn (Article $article) => [
                        'title' => $article->title,
                        'url' => ArticleUrl::show($article),
                    ])->values()->all(),
                ];
            });

        return Inertia::render('admin/Uploads', [
            'uploads' => $uploads,
            'totalSize' => (int) Upload::query()->sum('size'),
        ]);
    }

    public function destroy(Upload $upload): RedirectResponse
    {
        $this->uploads->delete($upload);

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$upload->original_name}」を削除しました。"]);

        return back();
    }
}
