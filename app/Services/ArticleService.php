<?php

namespace App\Services;

use App\Enums\VersionKind;
use App\Exceptions\ArticleTitleTakenException;
use App\Exceptions\ArticleVersionConflictException;
use App\Markdown\WikiMarkdown;
use App\Models\Article;
use App\Models\ArticleLink;
use App\Models\ArticleRedirect;
use App\Models\User;
use App\Support\TitleNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Wiki ページの作成・更新・解決と、WikiLink の追跡。
 *
 * タイトルの一意性判定・解決・リンク追跡・リダイレクトは、すべて照合キー
 * （Article::titleKey()）の完全一致で行い、DB の照合順序に依存しない。
 */
class ArticleService
{
    public const HOME_TITLE = 'ホーム';

    /** ヘルプページ（システムページ）の表示名。通常のページのタイトルとは別の名前空間なので衝突しない */
    public const HELP_TITLE = 'ヘルプ';

    /** 本文の上限（バイト） */
    public const MAX_BODY_BYTES = 1_048_576;

    public function __construct(private WikiMarkdown $markdown) {}

    /** タイトルの正規化（TitleNormalizer::normalize() を参照） */
    public function normalizeTitle(string $title): string
    {
        return TitleNormalizer::normalize($title);
    }

    /** URL セグメント（スペースは `_`）からタイトルへ復元する */
    public function urlToTitle(string $segment): string
    {
        return TitleNormalizer::fromUrlSegment($segment);
    }

    /** タイトルを URL セグメントに変換する（スペース → `_`） */
    public function titleToUrl(string $title): string
    {
        return TitleNormalizer::toUrlSegment($title);
    }

    /** 正規化前のタイトルから照合キーを作る */
    public function keyOf(string $title): string
    {
        return Article::titleKey($this->normalizeTitle($title));
    }

    public function isHome(Article $article): bool
    {
        return ! $article->isSystem() && $article->title_key === Article::titleKey(self::HOME_TITLE);
    }

    /**
     * ヘルプページ（システムページ）。無ければ初期内容（resources/markdown/help.md）で作る。
     * 初期設定済みの環境にも、最初に表示したときに用意される
     */
    public function help(): Article
    {
        $help = Article::system(Article::SYSTEM_HELP)->first();

        if ($help) {
            return $help;
        }

        try {
            return DB::transaction(function () {
                $body = (string) file_get_contents(resource_path('markdown/help.md'));

                $help = new Article(['title' => self::HELP_TITLE, 'body' => $body]);
                $help->system_key = Article::SYSTEM_HELP;
                $help->save();

                $help->versions()->create([
                    'user_id' => null,
                    'body' => $body,
                    'summary' => '初期ページ',
                    'version_number' => 1,
                ]);

                $this->syncLinks($help);

                return $help;
            });
        } catch (UniqueConstraintViolationException) {
            // 同時に表示した別のリクエストが先に作った
            return Article::system(Article::SYSTEM_HELP)->firstOrFail();
        }
    }

    /**
     * 本文から WikiLink のリンク先タイトル（正規化済み）を抽出する。
     * 描画と同じ Markdown 解析を使うため、コードブロック・インラインコード内は対象外になる。
     *
     * @return list<string> 照合キーで重複排除した正規化タイトル
     */
    public function extractLinkTitles(string $body): array
    {
        return $this->markdown->linkTargets($body);
    }

    /** ページ保存時にリンクテーブルを洗い替える */
    public function syncLinks(Article $article): void
    {
        $keys = array_values(array_unique(array_map(
            fn (string $title) => Article::titleKey($title),
            $this->extractLinkTitles($article->body),
        )));

        DB::transaction(function () use ($article, $keys) {
            ArticleLink::where('from_article_id', $article->id)->delete();

            $now = now();
            $rows = array_map(fn (string $key) => [
                'from_article_id' => $article->id,
                'to_title_key' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ], $keys);

            if ($rows) {
                ArticleLink::insert($rows);
            }
        });
    }

    /**
     * タイトルからページを解決する。リダイレクトも辿る。
     *
     * 照合キーで一致したページの正式表記が要求と異なる場合（大文字小文字の違いや改名前タイトル）、
     * 呼び出し側は正式表記の URL へリダイレクトする。
     *
     * @return array{article: ?Article, redirectedFrom: ?string}
     */
    public function resolve(string $title): array
    {
        $title = $this->normalizeTitle($title);
        $key = Article::titleKey($title);

        $article = Article::where('title_key', $key)->first();

        if ($article) {
            return ['article' => $article, 'redirectedFrom' => null];
        }

        $target = ArticleRedirect::where('old_title_key', $key)->first()?->article;

        if ($target) {
            return ['article' => $target, 'redirectedFrom' => $title];
        }

        return ['article' => null, 'redirectedFrom' => null];
    }

    /** 最新の版番号（版が無ければ 0） */
    public function latestVersionNumber(Article $article): int
    {
        return (int) $article->versions()->max('version_number');
    }

    /**
     * 新規ページを作成し、初版を保存する
     *
     * @param  array{title: string, body: string, summary?: ?string}  $data
     */
    public function create(array $data, User $user): Article
    {
        return DB::transaction(function () use ($data, $user) {
            $article = Article::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $article->versions()->create([
                'user_id' => $user->id,
                'body' => $data['body'],
                'summary' => $data['summary'] ?? null,
                'version_number' => 1,
            ]);

            // 同名ページができたので、同じタイトルからのリダイレクトは不要になる
            $this->forgetRedirectsTo($article->title_key);
            $this->syncLinks($article);

            return $article;
        });
    }

    /**
     * ページを更新し、新しい版を保存する。タイトル変更時はリダイレクトを登録する。
     *
     * @param  array{title: string, body: string, summary?: ?string}  $data
     * @param  int  $baseVersion  編集を始めた時点の版番号（楽観ロック）
     *
     * @throws ArticleVersionConflictException 編集中に他の版が保存されていたとき
     */
    public function update(Article $article, array $data, User $user, int $baseVersion): Article
    {
        try {
            return DB::transaction(function () use ($article, $data, $user, $baseVersion) {
                $latestVersion = $this->latestVersionNumber($article);
                if ($latestVersion !== $baseVersion) {
                    throw new ArticleVersionConflictException($article, $latestVersion);
                }

                $oldTitle = $article->title;
                $oldKey = $article->title_key;

                $article->update([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'updated_by' => $user->id,
                ]);

                if ($oldKey !== $article->title_key) {
                    $this->registerRedirect($article, $oldTitle);
                }

                $article->versions()->create([
                    'user_id' => $user->id,
                    'body' => $data['body'],
                    'summary' => $data['summary'] ?? null,
                    'version_number' => $latestVersion + 1,
                ]);

                $this->syncLinks($article);

                return $article->fresh();
            });
        } catch (UniqueConstraintViolationException $e) {
            // 版番号の照合と保存の間に、同時に保存された版と番号が衝突した。
            // ロールバック済みなので、メモリ上の変更を捨てて DB の最新状態に戻す
            $article->refresh();
            $latestVersion = $this->latestVersionNumber($article);
            if ($latestVersion > $baseVersion) {
                throw new ArticleVersionConflictException($article, $latestVersion);
            }

            throw $e;
        }
    }

    /** 旧タイトル → 改名後ページのリダイレクトを登録する */
    private function registerRedirect(Article $article, string $oldTitle): void
    {
        $oldKey = Article::titleKey($oldTitle);

        // 旧タイトルで引き続き存在するページがあればリダイレクトは不要
        if (! Article::where('title_key', $oldKey)->exists()) {
            // 旧タイトルへ向いていた既存リダイレクトがあれば、このページへ付け替える
            ArticleRedirect::updateOrCreate(
                ['old_title_key' => $oldKey],
                ['article_id' => $article->id],
            );
        }

        // 新タイトルと同じ照合キーのリダイレクトは、実在ページに隠れて使われなくなるので削除する
        $this->forgetRedirectsTo($article->title_key);
    }

    /** 実在ページと同じ照合キーを持つリダイレクトを削除する */
    private function forgetRedirectsTo(string $titleKey): void
    {
        ArticleRedirect::where('old_title_key', $titleKey)->delete();
    }

    /** 任意の版の内容を新しい版として復元する */
    public function restoreVersion(Article $article, int $versionNumber, User $user): Article
    {
        $version = $article->versions()->where('version_number', $versionNumber)->firstOrFail();

        return DB::transaction(function () use ($article, $version, $user) {
            $article->update([
                'body' => $version->body,
                'updated_by' => $user->id,
            ]);

            $article->versions()->create([
                'user_id' => $user->id,
                'body' => $version->body,
                'summary' => "v{$version->version_number} の内容に復元",
                'version_number' => $this->latestVersionNumber($article) + 1,
            ]);

            $this->syncLinks($article);

            return $article->fresh();
        });
    }

    /**
     * ページを削除（ソフトデリート）し、削除の版を記録する。
     * 本文・履歴・リンク表・改名前タイトルからのリダイレクトは復元に備えて残す。
     */
    public function delete(Article $article, User $user, ?string $summary = null): void
    {
        DB::transaction(function () use ($article, $user, $summary) {
            $this->recordVersion($article, $user, VersionKind::Delete, $summary);

            // SoftDeletes の delete() は deleted_at だけを書くので、削除者は先に保存する。
            // updated_at は最後に編集した日時のまま残す（削除日時は deleted_at にある）
            Article::withoutTimestamps(function () use ($article, $user) {
                $article->update(['deleted_by' => $user->id]);
                $article->delete();
            });
        });
    }

    /**
     * 削除・復元の版を記録する。本文は現在の本文のまま（直前の版との差分は無い）
     */
    private function recordVersion(Article $article, User $user, VersionKind $kind, ?string $summary = null): void
    {
        $article->versions()->create([
            'user_id' => $user->id,
            'body' => $article->body,
            'summary' => $summary,
            'version_number' => $this->latestVersionNumber($article) + 1,
            'kind' => $kind,
        ]);
    }

    /**
     * 削除済みページを復元し、復元の版を記録する。本文・履歴・リンク表・改名前タイトルからの
     * リダイレクトは削除時のまま残っているので、そのまま有効に戻る。
     *
     * @throws ArticleTitleTakenException 削除後に同じタイトルのページが作られていたとき
     */
    public function restoreDeleted(Article $article, User $user): Article
    {
        return DB::transaction(function () use ($article, $user) {
            $existing = Article::where('title_key', $article->title_key)->first();

            if ($existing) {
                throw new ArticleTitleTakenException($article, $existing);
            }

            try {
                // restore() の保存で削除者の記録も消す。updated_at は最後に編集した日時のまま残す
                $article->deleted_by = null;
                Article::withoutTimestamps(fn () => $article->restore());
            } catch (UniqueConstraintViolationException) {
                // 照合と復元の間に同名ページが作られた（active_title_key のユニーク制約）
                $article->refresh();
                throw new ArticleTitleTakenException($article, Article::where('title_key', $article->title_key)->firstOrFail());
            }

            $this->recordVersion($article, $user, VersionKind::Restore);

            // 削除中に同じタイトルから別ページへ張られたリダイレクトは、実在ページに隠れるので削除する
            $this->forgetRedirectsTo($article->title_key);

            return $article;
        });
    }

    /**
     * 本文中の WikiLink のうち、リンク先が実在する（またはリダイレクトがある）ものの照合キー。
     * 閲覧時の赤リンク判定に使う。
     *
     * @return list<string>
     */
    public function existingLinkKeys(string $body): array
    {
        return $this->markdown->existingKeys(
            array_map(fn (string $title) => Article::titleKey($title), $this->extractLinkTitles($body)),
        );
    }

    /**
     * 本文を HTML と目次に描画する（閲覧・編集プレビュー共通）
     *
     * @return array{html: string, toc: list<array{level: int, text: string, id: string}>}
     */
    public function render(string $body): array
    {
        return $this->markdown->render($body);
    }

    /**
     * 2 つの版の本文を描画し、変わったブロックに印を付ける（差分画面のプレビュー比較用）
     *
     * @return array{old: string, new: string}
     */
    public function renderComparison(string $oldBody, string $newBody): array
    {
        return $this->markdown->renderComparison($oldBody, $newBody);
    }

    /**
     * このページへリンクしている他ページ（被リンク）。改名前タイトルへのリンクも含む。
     *
     * @return list<array{id: int, title: string}>
     */
    public function backlinks(Article $article): array
    {
        // システムページは通常のタイトルの名前空間に無いので、[[ ]] でリンクされることは無い
        if ($article->isSystem()) {
            return [];
        }

        $keys = $article->redirects()->pluck('old_title_key')->push($article->title_key);

        return array_values(Article::query()
            ->whereKeyNot($article->id)
            ->whereIn('id', ArticleLink::whereIn('to_title_key', $keys)->select('from_article_id'))
            ->orderBy('title_key')
            ->get(['id', 'title'])
            ->map(fn (Article $a) => ['id' => $a->id, 'title' => $a->title])
            ->all());
    }

    /**
     * Wiki の初期ページ群を作成する。
     * ホームページに加えて、相互にリンクし合うチュートリアル用ページを用意し、
     * WikiLink や Markdown 記法の使い方を実例で学べるようにする。
     * $withSamples が false のときはホームだけを作る（ホームの本文にも目次を入れない）。
     */
    public function createHomePage(User $user, string $wikiName, bool $withSamples = true): Article
    {
        $home = $this->create([
            'title' => self::HOME_TITLE,
            'body' => $this->homePageBody($wikiName, $withSamples),
            'summary' => '初期ページ',
        ], $user);

        if (! $withSamples) {
            return $home;
        }

        // ホームから [[ ]] でリンクされるチュートリアルページ群
        $this->create([
            'title' => 'Wikiの使い方',
            'body' => $this->usagePageBody(),
            'summary' => '初期ページ',
        ], $user);

        $this->create([
            'title' => 'Markdown記法',
            'body' => $this->markdownPageBody(),
            'summary' => '初期ページ',
        ], $user);

        $this->create([
            'title' => 'サンプルページ',
            'body' => $this->samplePageBody(),
            'summary' => '初期ページ',
        ], $user);

        return $home;
    }

    private function homePageBody(string $wikiName, bool $withSamples): string
    {
        $intro = <<<MD
        # {$wikiName}

        ここは情報を自由に書き残せる Wiki です。
        画面上部の **編集** から、このページを書き換えてはじめましょう。
        MD;

        if (! $withSamples) {
            return $intro;
        }

        return <<<MD
        {$intro}

        ## 目次

        - [[Wikiの使い方]] — リンクの貼り方・編集・履歴などの基本操作
        - [[Markdown記法]] — 見出し・リスト・表などの書き方の早見表
        - [[サンプルページ]] — 実際にどう表示されるかの見本
        MD;
    }

    private function usagePageBody(): string
    {
        return <<<'MD'
        # Wikiの使い方

        [[ホーム]] に戻る

        ## ページ間のリンク

        - `[[ページ名]]` … そのページへのリンクを作ります。
        - `[[表示する文字|ページ名]]` … リンク文字を変えたいときはパイプ `|` で区切ります。
          例: `[[記法の早見表|Markdown記法]]` → [[記法の早見表|Markdown記法]]
        - 存在しないページへのリンクは **赤リンク** になり、クリックするとそのタイトルで新規作成できます。

        ## 閲覧・編集・履歴

        各ページ上部で切り替えます。

        1. **閲覧** … 通常の表示。
        2. **編集** … 本文を書き換えて保存します。保存時に「編集要約」を1行残せます。
        3. **履歴** … 過去の版の一覧・差分の確認・任意の版への復元ができます。

        ## ページを探す

        画面左上のメニューから次の特殊ページを開けます。

        - **最近の更新** … 更新された順にページが並びます。
        - **全ページ一覧** … 五十音・ABC 順の索引です。
        - **検索** … タイトルと本文から部分一致で検索できます。

        次は [[Markdown記法]] で書き方を覚えましょう。
        MD;
    }

    private function markdownPageBody(): string
    {
        return <<<'MD'
        # Markdown記法

        [[ホーム]] / [[Wikiの使い方]]

        Wiki の本文は Markdown で書けます。よく使う記法をまとめました。

        ## 見出し

        `#` の数で見出しのレベルが変わります（`#` 〜 `######`）。

        ## 文字装飾

        - `**太字**` → **太字**
        - `*斜体*` → *斜体*
        - `~~打ち消し~~` → ~~打ち消し~~
        - `` `インラインコード` `` → `code`

        ## リスト

        ```
        - 箇条書き
          - ネストもできます
        1. 番号付き
        2. リスト
        ```

        ## チェックリスト

        - [x] 完了したタスク
        - [ ] これからのタスク

        ## 表

        | 項目 | 説明 |
        | --- | --- |
        | 見出し | `#` で書く |
        | リンク | `[[ページ名]]` で書く |

        ## 引用・コードブロック

        > 引用はこのように表示されます。

        ```php
        // コードブロックは ``` で囲みます
        echo 'Hello, Wiki';
        ```

        実際の表示は [[サンプルページ]] でも確認できます。
        MD;
    }

    private function samplePageBody(): string
    {
        return <<<'MD'
        # サンプルページ

        [[ホーム]] / [[Wikiの使い方]] / [[Markdown記法]]

        このページは記法の表示見本です。気軽に書き換えたり、削除したりして構いません。

        ## ToDo の例

        - [x] Wiki を設置する
        - [x] ホームページを読む
        - [ ] 自分用のページを作る

        ## 用語集の例

        | 用語 | 意味 |
        | --- | --- |
        | WikiLink | `[[ ]]` で書くページ間リンク |
        | 赤リンク | まだ存在しないページへのリンク |
        | 編集要約 | 保存時に残す変更メモ |

        ## メモ

        > リンクをたどってページを増やしていくと、知識がつながっていきます。

        関連ページ: [[Wikiの使い方]] ・ [[Markdown記法]]
        MD;
    }
}
