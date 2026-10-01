<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    IconAlertCircle,
    IconFilePlus,
    IconLayoutSidebarRightExpand,
    IconLink,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import PageTabs from '@/components/wiki/PageTabs.vue';
import TableOfContents from '@/components/wiki/TableOfContents.vue';
import WikiBody from '@/components/wiki/WikiBody.vue';
import { formatDate, formatDateTime } from '@/lib/datetime';
import { login } from '@/routes';
import { index as deletedPagesIndex } from '@/routes/admin/deleted-pages';
import type { ArticleSummary, PublicUser, TocItem } from '@/types';

type ShownArticle = ArticleSummary & {
    body: string;
    created_at: string | null;
    updated_at: string | null;
    creator: PublicUser | null;
    updater: PublicUser | null;
};

const props = defineProps<{
    title: string;
    article: ShownArticle | null;
    // 以下は既存ページのときだけ渡される
    html?: string;
    toc?: TocItem[];
    isHome?: boolean;
    backlinks?: ArticleSummary[];
    versionCount?: number;
    canEdit?: boolean;
    // 閲覧・編集・履歴のタブを出すか（ヘルプは編集できる管理者にだけ出す）
    showTabs?: boolean;
    // 最終更新の時刻・更新者と版数を出すか（ヘルプを編集できない利用者には日付だけ）
    detailedMeta?: boolean;
    // 未作成ページのときだけ渡される
    canCreate?: boolean;
    hasDeleted?: boolean;
    urls: { edit: string; history?: string };
}>();

const page = usePage();
const redirectedFrom = computed(() => page.flash.redirectedFrom);

// 目次は見出しが 3 つ以上のときだけ出す
const showToc = computed(() => (props.toc?.length ?? 0) >= 3);

// 目次の折りたたみはブラウザに記憶し、ほかのページでも引き継ぐ
const TOC_STORAGE_KEY = 'metiwiki.toc.collapsed';

function storedTocCollapsed(): boolean {
    try {
        return localStorage.getItem(TOC_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

const tocCollapsed = ref(storedTocCollapsed());

watch(tocCollapsed, (collapsed) => {
    try {
        localStorage.setItem(TOC_STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
        // 保存できない環境（プライベートブラウズなど）では記憶しない
    }
});
</script>

<template>
    <Head :title="title" />

    <!-- 未作成のページ -->
    <div
        v-if="!article"
        class="rounded-lg border bg-card px-6 py-12 text-center shadow-xs"
        data-test="missing-page"
    >
        <IconAlertCircle class="mx-auto mb-3 size-10 text-amber-500" />
        <h1 class="mb-1 text-xl font-semibold break-words">{{ title }}</h1>
        <p class="mb-6 text-muted-foreground">このページはまだ存在しません。</p>
        <Button v-if="canCreate" as-child>
            <Link :href="urls.edit" class="gap-1.5">
                <IconFilePlus class="size-4" />
                このページを作成する
            </Link>
        </Button>
        <p v-else class="text-sm text-muted-foreground">
            ページを作成するには<Link
                :href="login({ query: { redirect: urls.edit } })"
                class="underline underline-offset-2"
                >ログイン</Link
            >してください。
        </p>
        <p
            v-if="hasDeleted"
            class="mt-6 text-sm text-muted-foreground"
            data-test="has-deleted"
        >
            このタイトルの削除済みページがあります。<Link
                :href="deletedPagesIndex()"
                class="underline underline-offset-2"
                >削除済みページ</Link
            >から復元できます。
        </p>
    </div>

    <!-- 既存のページ -->
    <template v-else>
        <PageTabs
            v-if="showTabs"
            active="view"
            :urls="{
                view: article.url,
                edit: canEdit ? urls.edit : null,
                history: urls.history,
            }"
        />

        <p
            v-if="redirectedFrom"
            class="mb-3 text-sm text-muted-foreground"
            data-test="redirected-from"
        >
            「{{ redirectedFrom }}」から転送されました
        </p>

        <div class="flex" :class="tocCollapsed ? 'gap-3' : 'gap-8'">
            <!-- 記事の本体は白いカードに載せ、色付きの背景から浮かせる -->
            <article
                class="min-w-0 flex-1 rounded-lg border bg-card px-5 py-6 text-card-foreground shadow-xs sm:px-8"
            >
                <h1 class="mb-1 text-3xl font-bold break-words">
                    {{ article.title }}
                </h1>
                <p
                    class="mb-6 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                >
                    <span v-if="article.updated_at && !detailedMeta"
                        >最終更新: {{ formatDate(article.updated_at) }}</span
                    >
                    <span v-else-if="article.updated_at"
                        >最終更新: {{ formatDateTime(article.updated_at) }}
                        <template v-if="article.updater"
                            >（{{ article.updater.name }}）</template
                        ></span
                    >
                    <span v-if="detailedMeta && versionCount"
                        >{{ versionCount }} 版</span
                    >
                </p>

                <WikiBody :html="html ?? ''" />

                <section
                    v-if="backlinks?.length"
                    class="mt-10 rounded-lg border"
                    aria-labelledby="backlinks-heading"
                >
                    <h2
                        id="backlinks-heading"
                        class="flex items-center gap-1.5 border-b px-4 py-2.5 text-sm font-medium"
                    >
                        <IconLink class="size-4 text-muted-foreground" />
                        このページへのリンク
                    </h2>
                    <ul class="divide-y">
                        <li v-for="backlink in backlinks" :key="backlink.id">
                            <Link
                                :href="backlink.url"
                                class="block px-4 py-2 text-sm transition-colors hover:bg-accent"
                                >{{ backlink.title }}</Link
                            >
                        </li>
                    </ul>
                </section>
            </article>

            <aside
                v-if="showToc"
                class="hidden shrink-0 lg:block"
                :class="tocCollapsed ? 'w-9' : 'w-56'"
            >
                <!-- 折りたたんでいるときは開くボタンだけを置き、本文を広げる -->
                <Button
                    v-if="tocCollapsed"
                    variant="outline"
                    size="icon"
                    class="sticky top-[calc(var(--header-height)+1rem)] size-9"
                    title="目次を開く"
                    data-test="toc-expand"
                    @click="tocCollapsed = false"
                >
                    <IconLayoutSidebarRightExpand class="size-4" />
                    <span class="sr-only">目次を開く</span>
                </Button>
                <TableOfContents
                    v-else
                    :items="toc ?? []"
                    collapsible
                    class="sticky top-[calc(var(--header-height)+1rem)] max-h-[calc(100svh-var(--header-height)-2rem)] overflow-y-auto"
                    @collapse="tocCollapsed = true"
                />
            </aside>
        </div>
    </template>
</template>
