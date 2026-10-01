<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconHistory } from '@tabler/icons-vue';
import PageTabs from '@/components/wiki/PageTabs.vue';
import RestoreVersionButton from '@/components/wiki/RestoreVersionButton.vue';
import WikiBody from '@/components/wiki/WikiBody.vue';
import { formatDateTime } from '@/lib/datetime';
import type {
    ArticleSummary,
    PageUrls,
    TocItem,
    VersionWithBody,
} from '@/types';

defineProps<{
    article: ArticleSummary;
    urls: PageUrls;
    version: VersionWithBody;
    html: string;
    toc: TocItem[];
    latestVersion: number;
    canRestore: boolean;
}>();
</script>

<template>
    <Head :title="`${article.title}（v${version.version_number}）`" />

    <PageTabs active="history" :urls="urls" />

    <div
        class="mb-6 flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 px-4 py-3 text-sm"
        data-test="old-version-notice"
    >
        <IconHistory class="size-4 shrink-0 text-muted-foreground" />
        <div class="min-w-0 flex-1">
            <p>
                <span class="font-semibold">v{{ version.version_number }}</span>
                （{{ formatDateTime(version.created_at) }}・{{
                    version.user?.name ?? '削除されたユーザー'
                }}）の内容を表示しています。
                <template v-if="version.version_number !== latestVersion">
                    <Link
                        :href="article.url"
                        class="underline underline-offset-2"
                        >最新版（v{{ latestVersion }}）を見る</Link
                    >
                </template>
                <template v-else>これが最新版です。</template>
            </p>
            <p
                v-if="version.summary"
                class="mt-0.5 text-xs text-muted-foreground"
            >
                編集要約: {{ version.summary }}
            </p>
        </div>
        <RestoreVersionButton
            v-if="canRestore && version.version_number !== latestVersion"
            :article-id="article.id"
            :version-number="version.version_number"
        />
    </div>

    <article
        class="rounded-lg border bg-card px-5 py-6 text-card-foreground shadow-xs sm:px-8"
    >
        <h1 class="mb-6 text-3xl font-bold break-words">{{ article.title }}</h1>
        <WikiBody :html="html" />
    </article>
</template>
