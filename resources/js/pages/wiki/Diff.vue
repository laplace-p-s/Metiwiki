<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconArrowRight } from '@tabler/icons-vue';
import { ref, watch } from 'vue';
import LineDiffView from '@/components/wiki/LineDiffView.vue';
import PageTabs from '@/components/wiki/PageTabs.vue';
import WikiBody from '@/components/wiki/WikiBody.vue';
import { formatDateTime } from '@/lib/datetime';
import type { ArticleSummary, PageUrls, VersionWithBody } from '@/types';

type DiffVersion = VersionWithBody & { url: string };

defineProps<{
    article: ArticleSummary;
    urls: PageUrls;
    from: DiffVersion;
    to: DiffVersion;
    // 両方の版をサーバーで描画した HTML。marked のとき（隣り合う 2 版）は変わったブロックに印が付いている
    preview: { fromHtml: string; toHtml: string; marked: boolean };
    latestVersion: number;
}>();

// ソース（Markdown の行差分）/ プレビュー（描画した 2 版を左右に並べる）の選択はブラウザに記憶する
const MODE_STORAGE_KEY = 'metiwiki.diff.mode';

function storedMode(): 'source' | 'preview' {
    try {
        return localStorage.getItem(MODE_STORAGE_KEY) === 'preview'
            ? 'preview'
            : 'source';
    } catch {
        return 'source';
    }
}

const mode = ref(storedMode());

watch(mode, (value) => {
    try {
        localStorage.setItem(MODE_STORAGE_KEY, value);
    } catch {
        // 保存できない環境（プライベートブラウズなど）では記憶しない
    }
});

const modes = [
    { key: 'source', label: 'ソース' },
    { key: 'preview', label: 'プレビュー' },
] as const;
</script>

<template>
    <Head
        :title="`${article.title}（v${from.version_number} と v${to.version_number} の差分）`"
    />

    <PageTabs active="history" :urls="urls" />

    <h1 class="mb-4 text-xl font-semibold break-words">
        {{ article.title }} の差分
    </h1>

    <div class="mb-4 grid gap-3 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
        <div
            v-for="(version, i) in [from, to]"
            :key="i"
            class="rounded-lg border bg-card px-4 py-3 text-sm shadow-xs"
            :class="{ 'sm:order-3': i === 1 }"
        >
            <div class="flex items-center gap-2">
                <Link
                    :href="version.url"
                    class="font-mono font-semibold hover:underline"
                    >v{{ version.version_number }}</Link
                >
                <span
                    v-if="version.version_number === latestVersion"
                    class="text-xs text-muted-foreground"
                    >（最新）</span
                >
            </div>
            <p class="mt-0.5 text-xs text-muted-foreground">
                {{ formatDateTime(version.created_at) }}・{{
                    version.user?.name ?? '削除されたユーザー'
                }}
            </p>
            <p v-if="version.summary" class="mt-0.5 truncate text-xs">
                {{ version.summary }}
            </p>
        </div>
        <IconArrowRight
            class="mx-auto hidden size-5 text-muted-foreground sm:order-2 sm:block"
        />
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-3">
        <span
            class="inline-flex overflow-hidden rounded-md border text-xs"
            role="group"
            aria-label="比較の表示"
        >
            <button
                v-for="item in modes"
                :key="item.key"
                type="button"
                class="px-3 py-1 transition-colors"
                :class="
                    mode === item.key
                        ? 'bg-accent font-medium text-foreground'
                        : 'bg-background text-muted-foreground hover:text-foreground'
                "
                :aria-pressed="mode === item.key"
                :data-test="`diff-mode-${item.key}`"
                @click="mode = item.key"
            >
                {{ item.label }}
            </button>
        </span>
        <p v-if="mode === 'preview'" class="text-xs text-muted-foreground">
            <template v-if="preview.marked"
                ><mark class="wiki-diff-legend">黄色</mark
                >の部分が変わった箇所です。書式やリンク先だけの変更、丸ごとの追加・削除は、段落・見出し・表などの全体を塗ります。</template
            >
            <template v-else
                >隣り合う版どうしのときだけ、変わった箇所を強調します。</template
            >
        </p>
    </div>

    <LineDiffView
        v-if="mode === 'source'"
        :old-text="from.body"
        :new-text="to.body"
    />

    <!-- プレビュー: 2 つの版を描画して左右に並べる（狭い画面では上下） -->
    <div v-else class="grid gap-4 lg:grid-cols-2" data-test="diff-preview">
        <article
            v-for="(html, i) in [preview.fromHtml, preview.toHtml]"
            :key="i"
            class="min-w-0 rounded-lg border bg-card px-5 py-5 text-card-foreground shadow-xs"
        >
            <p
                class="mb-4 border-b pb-2 font-mono text-xs font-semibold text-muted-foreground"
            >
                v{{ (i === 0 ? from : to).version_number }}
            </p>
            <WikiBody :html="html" />
        </article>
    </div>
</template>
