<?php

namespace App\Http\Controllers;

use App\Enums\VersionKind;
use App\Exceptions\ArticleVersionConflictException;
use App\Http\Requests\Article\DestroyArticleRequest;
use App\Http\Requests\Article\StoreArticleRequest;
use App\Http\Requests\Article\UpdateArticleRequest;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\User;
use App\Rules\ArticleTitle;
use App\Services\ArticleSearchService;
use App\Services\ArticleService;
use App\Services\UploadService;
use App\Support\ArticleUrl;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function __construct(private ArticleService $service) {}

    /** トップ（/）はホームページを直接表示する */
    public function home(Request $request): Response
    {
        $article = $this->service->resolve(ArticleService::HOME_TITLE)['article'];

        return $this->renderShow($request, $article, ArticleService::HOME_TITLE);
    }

    public function show(Request $request, string $title): Response|RedirectResponse
    {
        [$article, $resolvedTitle, $redirect] = $this->locate($title, ArticleUrl::show(...), homeIsRoot: true);

        return $redirect ?? $this->renderShow($request, $article, $resolvedTitle);
    }

    public function edit(string $title): Response|RedirectResponse
    {
        [$article, $resolvedTitle, $redirect] = $this->locate($title, ArticleUrl::edit(...));

        if ($redirect) {
            return $redirect;
        }

        if (! $article) {
            Gate::authorize('create', Article::class);

            return Inertia::render('wiki/Edit', [
                'isNew' => true,
                'article' => null,
                'title' => $resolvedTitle,
                'body' => '',
                'version' => 0,
                'titleLocked' => false,
                'pageTitles' => $this->pageTitles(),
                'uploadMaxBytes' => UploadService::maxBytes(),
                'canDelete' => false,
                'urls' => ['show' => ArticleUrl::show($resolvedTitle)],
            ]);
        }

        return $this->renderEdit($article);
    }

    /** ヘルプ（/-/help）。見た目は通常のページと同じで、タブは編集できる管理者にだけ出す */
    public function helpShow(Request $request): Response
    {
        return $this->renderShow($request, $this->service->help(), ArticleService::HELP_TITLE);
    }

    public function helpEdit(): Response
    {
        return $this->renderEdit($this->service->help());
    }

    public function helpHistory(): Response
    {
        return $this->renderHistory($this->service->help());
    }

    public function helpVersion(int $version): Response
    {
        return $this->renderVersion($this->service->help(), $version);
    }

    public function helpDiff(Request $request): Response
    {
        [$from, $to] = $this->diffRange($request);

        return $this->renderDiff($this->service->help(), $from, $to);
    }

    private function renderEdit(Article $article): Response
    {
        Gate::authorize('update', $article);

        return Inertia::render('wiki/Edit', [
            'isNew' => false,
            'article' => ['id' => $article->id, 'title' => $article->title],
            'title' => $article->title,
            'body' => $article->body,
            'version' => $this->service->latestVersionNumber($article),
            // ホームページとヘルプは改名できない
            'titleLocked' => $this->service->isHome($article) || $article->isSystem(),
            'pageTitles' => $this->pageTitles($article),
            'uploadMaxBytes' => UploadService::maxBytes(),
            // 削除ボタンは編集タブに置く
            'canDelete' => Gate::allows('delete', $article),
            'urls' => [
                'show' => ArticleUrl::show($article),
                'edit' => ArticleUrl::edit($article),
                'history' => ArticleUrl::history($article),
            ],
        ]);
    }

    /**
     * 新規ページ作成の入口。タイトルの正規化と URL の組み立てをサーバー側に集約するため、
     * 画面からは入力されたタイトルをそのまま送ってもらい、編集画面へリダイレクトする。
     * 同じタイトルのページが既にあれば、そのページの編集画面へ送る。
     */
    public function create(Request $request): RedirectResponse
    {
        Gate::authorize('create', Article::class);

        $title = $this->service->normalizeTitle((string) $request->query('title', ''));

        Validator::make(['title' => $title], [
            'title' => ['required', 'string', 'max:255', new ArticleTitle],
        ])->validate();

        $article = $this->service->resolve($title)['article'];

        return redirect(ArticleUrl::edit($article->title ?? $title));
    }

    public function store(StoreArticleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Article::class);

        $article = $this->service->create($request->articleData(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ページを作成しました。']);

        return redirect(ArticleUrl::show($article->title));
    }

    public function update(UpdateArticleRequest $request, Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);

        try {
            $article = $this->service->update($article, $request->articleData(), $request->user(), $request->integer('version_number'));
        } catch (ArticleVersionConflictException $e) {
            // 編集内容はフォーム側に残したまま、最新版を並べて見せられるようにする
            Inertia::flash('conflict', [
                'latestBody' => $e->article->body,
                'latestVersion' => $e->latestVersion,
                'updatedBy' => $e->article->updater?->name,
            ]);

            return back()->withErrors([
                'version_number' => '編集中に他の人がこのページを更新しました。最新版を確認してから保存し直してください。',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ページを更新しました。']);

        return redirect(ArticleUrl::show($article));
    }

    public function destroy(DestroyArticleRequest $request, Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);

        $this->service->delete($article, $request->user(), $request->validated('summary'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "「{$article->title}」を削除しました。"]);

        return redirect(ArticleUrl::show(ArticleService::HOME_TITLE));
    }

    public function restoreVersion(Request $request, Article $article, int $version): RedirectResponse
    {
        Gate::authorize('update', $article);

        $this->service->restoreVersion($article, $version, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "バージョン {$version} の内容に戻しました。"]);

        return redirect(ArticleUrl::show($article));
    }

    public function history(string $title): Response|RedirectResponse
    {
        [$article, , $redirect] = $this->locate($title, ArticleUrl::history(...));

        if ($redirect) {
            return $redirect;
        }

        if (! $article) {
            abort(404);
        }

        return $this->renderHistory($article);
    }

    private function renderHistory(Article $article): Response
    {
        Gate::authorize('viewHistory', $article);

        $versions = $article->versions()
            ->with('user:id,name')
            ->paginate(50)
            ->through(fn (ArticleVersion $v) => [
                ...$this->versionSummary($v),
                'url' => ArticleUrl::version($article, $v->version_number),
                'diffUrl' => $this->diffUrlFor($article, $v),
            ]);

        return Inertia::render('wiki/History', [
            'article' => $this->articleSummary($article),
            'urls' => $this->pageUrls($article),
            'versions' => $versions,
            'latestVersion' => $this->service->latestVersionNumber($article),
            'canRestore' => Gate::allows('update', $article),
        ]);
    }

    public function version(string $title, int $version): Response|RedirectResponse
    {
        [$article, , $redirect] = $this->locate($title, fn (string $t) => ArticleUrl::version($t, $version));

        if ($redirect) {
            return $redirect;
        }

        if (! $article) {
            abort(404);
        }

        return $this->renderVersion($article, $version);
    }

    private function renderVersion(Article $article, int $version): Response
    {
        Gate::authorize('viewHistory', $article);

        $found = $article->versions()->with('user:id,name')->where('version_number', $version)->firstOrFail();

        return Inertia::render('wiki/Version', [
            'article' => $this->articleSummary($article),
            'urls' => $this->pageUrls($article),
            'version' => [...$this->versionSummary($found), 'body' => $found->body],
            ...$this->service->render($found->body),
            'latestVersion' => $this->service->latestVersionNumber($article),
            'canRestore' => Gate::allows('update', $article),
        ]);
    }

    public function diff(Request $request, string $title): Response|RedirectResponse
    {
        [$from, $to] = $this->diffRange($request);

        // 比較する版の指定を保ったまま正式 URL へリダイレクトする
        [$article, , $redirect] = $this->locate($title, fn (string $t) => ArticleUrl::diff($t, $from, $to));

        if ($redirect) {
            return $redirect;
        }

        if (! $article) {
            abort(404);
        }

        return $this->renderDiff($article, $from, $to);
    }

    /**
     * 差分画面の比較する版（?from=&to=）。省略した版は null
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function diffRange(Request $request): array
    {
        $validated = Validator::make($request->query(), [
            'from' => ['sometimes', 'integer', 'min:1'],
            'to' => ['sometimes', 'integer', 'min:1'],
        ])->validate();

        return [
            isset($validated['from']) ? (int) $validated['from'] : null,
            isset($validated['to']) ? (int) $validated['to'] : null,
        ];
    }

    private function renderDiff(Article $article, ?int $from, ?int $to): Response
    {
        Gate::authorize('viewHistory', $article);

        // 省略時は最新版と 1 つ前の版を比べる
        $latest = $this->service->latestVersionNumber($article);
        $to ??= $latest;
        $from ??= max(1, $to - 1);

        $versions = $article->versions()
            ->with('user:id,name')
            ->whereIn('version_number', [$from, $to])
            ->get()
            ->keyBy('version_number');

        abort_unless($versions->has($from) && $versions->has($to), 404);

        // プレビューでの比較。隣り合う 2 版のときだけ、変わったブロックを強調する
        $marked = $to === $from + 1;
        $preview = $marked
            ? $this->service->renderComparison($versions[$from]->body, $versions[$to]->body)
            : [
                'old' => $this->service->render($versions[$from]->body)['html'],
                'new' => $this->service->render($versions[$to]->body)['html'],
            ];

        return Inertia::render('wiki/Diff', [
            'article' => $this->articleSummary($article),
            'urls' => $this->pageUrls($article),
            'from' => [
                ...$this->versionSummary($versions[$from]),
                'body' => $versions[$from]->body,
                'url' => ArticleUrl::version($article, $from),
            ],
            'to' => [
                ...$this->versionSummary($versions[$to]),
                'body' => $versions[$to]->body,
                'url' => ArticleUrl::version($article, $to),
            ],
            'preview' => [
                'fromHtml' => $preview['old'],
                'toHtml' => $preview['new'],
                'marked' => $marked,
            ],
            'latestVersion' => $latest,
        ]);
    }

    public function recentChanges(): Response
    {
        // 削除済みページの編集は出さず、削除・復元の記録だけを出す。
        // システムページ（ヘルプ）はグローバルスコープで除かれる
        $versions = ArticleVersion::query()
            ->whereIn('article_id', Article::withTrashed()->select('id'))
            ->where(fn ($query) => $query
                ->whereIn('article_id', Article::query()->select('id'))
                ->orWhere('kind', '!=', VersionKind::Edit))
            ->with(['user:id,name', 'article:id,title,deleted_at'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (ArticleVersion $v) => [
                ...$this->versionSummary($v),
                'article' => $this->articleSummary($v->article),
                'isDeleted' => $v->article->trashed(),
                'diffUrl' => $v->article->trashed() ? null : $this->diffUrlFor($v->article, $v),
            ]);

        return Inertia::render('wiki/RecentChanges', [
            'versions' => $versions,
        ]);
    }

    public function allPages(): Response
    {
        $pages = Article::query()
            ->orderBy('title_key')
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (Article $a) => [
                ...$this->articleSummary($a),
                'updated_at' => $a->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('wiki/AllPages', [
            'pages' => $pages,
        ]);
    }

    public function search(Request $request, ArticleSearchService $searchService): Response
    {
        $query = trim((string) $request->query('q', ''));

        $results = array_map(fn (array $r) => [
            ...$r,
            'url' => ArticleUrl::show($r['title']),
        ], $searchService->search($query));

        return Inertia::render('wiki/Search', [
            'query' => $query,
            'results' => $results,
            // 同名ページが無ければ「このタイトルで作成」を出すため
            'exactMatch' => $query !== '' && $this->service->resolve($query)['article'] !== null,
            'createUrl' => $query !== '' && $this->isValidTitle($this->service->normalizeTitle($query))
                ? ArticleUrl::edit($this->service->normalizeTitle($query))
                : null,
        ]);
    }

    /**
     * URL のタイトルからページを解決する。正式表記の URL と違えばリダイレクトを返す。
     *
     * - 改名前のタイトル → 改名後のタイトル（「〜から転送」を表示するため旧タイトルをフラッシュ）
     * - 大文字小文字などの表記ゆれ、空白の入った URL → 正式表記
     * - ホームページ → トップ（/）
     *
     * @param  Closure(string): string  $urlFor  正式表記のタイトルから URL を作る
     * @param  bool  $homeIsRoot  ホームページを常にトップ（/）へリダイレクトするか（閲覧ルート用）
     * @return array{0: ?Article, 1: string, 2: ?RedirectResponse}
     */
    private function locate(string $segment, Closure $urlFor, bool $homeIsRoot = false): array
    {
        $title = $this->service->urlToTitle($segment);

        // 作成もできないタイトル（使えない文字を含むなど）は存在しない URL として扱う
        abort_unless($this->isValidTitle($title), 404);

        ['article' => $article, 'redirectedFrom' => $redirectedFrom] = $this->service->resolve($title);

        $canonicalTitle = $article->title ?? $title;
        $isCanonical = $segment === $this->service->titleToUrl($canonicalTitle)
            && ! ($homeIsRoot && $article && $this->service->isHome($article));

        if (! $isCanonical) {
            if ($redirectedFrom !== null) {
                Inertia::flash('redirectedFrom', $redirectedFrom);
            }

            return [$article, $canonicalTitle, redirect($urlFor($canonicalTitle))];
        }

        return [$article, $canonicalTitle, null];
    }

    private function isValidTitle(string $title): bool
    {
        return Validator::make(['title' => $title], ['title' => ['required', 'string', 'max:255', new ArticleTitle]])->passes();
    }

    private function renderShow(Request $request, ?Article $article, string $title): Response
    {
        if (! $article) {
            return Inertia::render('wiki/Show', [
                'title' => $title,
                'article' => null,
                'canCreate' => Gate::allows('create', Article::class),
                // 管理者には、同じタイトルの削除済みページがあれば復元の導線を出す
                'hasDeleted' => Gate::allows('admin')
                    && Article::onlyTrashed()->where('title_key', Article::titleKey($title))->exists(),
                'urls' => ['edit' => ArticleUrl::edit($title)],
            ]);
        }

        $article->load(['creator:id,name', 'updater:id,name']);

        // ヘルプを編集できない利用者には、最終更新の日付だけを見せる（更新者・版数・時刻は出さない）
        $detailedMeta = ! $article->isHelp() || Gate::allows('update', $article);

        return Inertia::render('wiki/Show', [
            'title' => $article->title,
            'article' => [
                ...$this->articleSummary($article),
                'body' => $article->body,
                'created_at' => $article->created_at?->toIso8601String(),
                'updated_at' => $article->updated_at?->toIso8601String(),
                'creator' => $detailedMeta ? $this->userSummary($article->creator) : null,
                'updater' => $detailedMeta ? $this->userSummary($article->updater) : null,
            ],
            'detailedMeta' => $detailedMeta,
            // 閲覧はサーバー描画（検索エンジンにも本文が載り、赤リンク判定もここで済ませる）
            ...$this->service->render($article->body),
            'isHome' => $this->service->isHome($article),
            'backlinks' => array_map(fn (array $b) => [
                ...$b,
                'url' => ArticleUrl::show($b['title']),
            ], $this->service->backlinks($article)),
            'versionCount' => $detailedMeta ? $article->versions()->count() : null,
            'canEdit' => Gate::allows('update', $article),
            // ヘルプのタブ（閲覧・編集・履歴）は、編集できる管理者にだけ出す
            'showTabs' => ! $article->isHelp() || Gate::allows('update', $article),
            'urls' => [
                'edit' => ArticleUrl::edit($article),
                'history' => ArticleUrl::history($article),
            ],
        ]);
    }

    /**
     * WikiLink の補完候補に使う既存ページのタイトル（編集中のページ自身は除く）
     *
     * @return list<string>
     */
    private function pageTitles(?Article $exclude = null): array
    {
        return array_values(Article::query()
            ->when($exclude, fn ($q) => $q->whereKeyNot($exclude->id))
            ->orderBy('title_key')
            ->get(['id', 'title'])
            ->map(fn (Article $a) => $a->title)
            ->all());
    }

    /**
     * 閲覧・編集・履歴タブの URL。編集できない利用者には編集タブを出さない（null）
     *
     * diff は比較する版を指定しない差分画面の URL（クエリで from・to を付けて使う）
     *
     * @return array{view: string, edit: ?string, history: string, diff: string}
     */
    private function pageUrls(Article $article): array
    {
        return [
            'view' => ArticleUrl::show($article),
            'edit' => Gate::allows('update', $article) ? ArticleUrl::edit($article) : null,
            'history' => ArticleUrl::history($article),
            'diff' => ArticleUrl::diff($article),
        ];
    }

    /**
     * @return array{id: int, title: string, url: string}
     */
    private function articleSummary(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'url' => ArticleUrl::show($article),
        ];
    }

    /** 直前の版との差分の URL。初版と、本文の変わらない削除・復元の版には無い */
    private function diffUrlFor(Article $article, ArticleVersion $version): ?string
    {
        return $version->kind === VersionKind::Edit && $version->version_number > 1
            ? ArticleUrl::diff($article, $version->version_number - 1, $version->version_number)
            : null;
    }

    /**
     * @return array{version_number: int, kind: string, summary: ?string, created_at: ?string, user: ?array{id: int, name: string}}
     */
    private function versionSummary(ArticleVersion $version): array
    {
        return [
            'version_number' => $version->version_number,
            'kind' => $version->kind->value,
            'summary' => $version->summary,
            'created_at' => $version->created_at?->toIso8601String(),
            'user' => $this->userSummary($version->user),
        ];
    }

    /**
     * 公開ページに載せるユーザー情報は名前だけにする（ログイン ID・メールアドレスは出さない）
     *
     * @return array{id: int, name: string}|null
     */
    private function userSummary(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
