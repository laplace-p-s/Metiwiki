// Wiki 画面の props の型（ArticleController が返す形に対応）

// 公開ページに載せるユーザー情報（名前のみ）
export type PublicUser = {
    id: number;
    name: string;
};

export type ArticleSummary = {
    id: number;
    title: string;
    url: string;
};

// 閲覧・編集・履歴タブと差分画面の URL（編集できない利用者には edit が null）
export type PageUrls = {
    view: string;
    edit: string | null;
    history: string;
    // 比較する版を指定しない差分画面の URL（?from=&to= を付けて使う）
    diff: string;
};

export type TocItem = {
    level: number;
    text: string;
    id: string;
};

export type RenderedMarkdown = {
    html: string;
    toc: TocItem[];
};

// 版の種別（App\Enums\VersionKind）。削除・復元の版の本文は直前の版と同じ
export type VersionKind = 'edit' | 'delete' | 'restore';

export type VersionSummary = {
    version_number: number;
    kind: VersionKind;
    summary: string | null;
    created_at: string | null;
    user: PublicUser | null;
};

export type VersionWithBody = VersionSummary & {
    body: string;
};

export type EditConflict = {
    latestBody: string;
    latestVersion: number;
    updatedBy: string | null;
};

// Laravel の LengthAwarePaginator を JSON にしたもの
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: { url: string | null; label: string; active: boolean }[];
};
