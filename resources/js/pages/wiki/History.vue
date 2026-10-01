<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconGitCompare } from '@tabler/icons-vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import PageTabs from '@/components/wiki/PageTabs.vue';
import Pagination from '@/components/wiki/Pagination.vue';
import RestoreVersionButton from '@/components/wiki/RestoreVersionButton.vue';
import VersionKindBadge from '@/components/wiki/VersionKindBadge.vue';
import { formatDateTime } from '@/lib/datetime';
import type {
    ArticleSummary,
    PageUrls,
    Paginated,
    VersionSummary,
} from '@/types';

type HistoryVersion = VersionSummary & {
    url: string;
    diffUrl: string | null;
};

const props = defineProps<{
    article: ArticleSummary;
    urls: PageUrls;
    versions: Paginated<HistoryVersion>;
    latestVersion: number;
    canRestore: boolean;
}>();

// 比較する版はチェックボックスで 2 つ選ぶ（古い方が比較元）。最初は最新版と 1 つ前の版を選んでおく。
// 3 つ目を選ぶと、先に選んでいた方から外す
const selected = ref<number[]>(
    props.latestVersion > 1
        ? [props.latestVersion - 1, props.latestVersion]
        : [],
);

function toggleSelected(versionNumber: number, checked: boolean) {
    const rest = selected.value.filter((n) => n !== versionNumber);
    selected.value = checked ? [...rest, versionNumber].slice(-2) : rest;
}

function compare() {
    const [from, to] = [...selected.value].sort((a, b) => a - b);

    router.get(props.urls.diff, { from, to });
}
</script>

<template>
    <Head :title="`${article.title}（履歴）`" />

    <PageTabs active="history" :urls="urls" />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-semibold break-words">
            {{ article.title }} の履歴
        </h1>
        <span class="text-sm text-muted-foreground"
            >{{ versions.total }} 版</span
        >
        <Button
            v-if="latestVersion > 1"
            variant="outline"
            size="sm"
            class="ml-auto gap-1.5"
            :disabled="selected.length !== 2"
            @click="compare"
        >
            <IconGitCompare class="size-4" />
            選んだ 2 つの版を比較
        </Button>
    </div>

    <ul class="divide-y rounded-lg border bg-card shadow-xs">
        <li
            v-for="version in versions.data"
            :key="version.version_number"
            class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3"
        >
            <Checkbox
                v-if="latestVersion > 1"
                :model-value="selected.includes(version.version_number)"
                :aria-label="`v${version.version_number} を比較する版に選ぶ`"
                data-test="compare-select"
                @update:model-value="
                    toggleSelected(version.version_number, $event === true)
                "
            />

            <Link
                :href="version.url"
                class="w-12 shrink-0 font-mono text-sm font-semibold hover:underline"
                >v{{ version.version_number }}</Link
            >

            <div class="min-w-0 flex-1">
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                >
                    <span class="font-medium">{{
                        version.user?.name ?? '（削除されたユーザー）'
                    }}</span>
                    <span class="text-xs text-muted-foreground">{{
                        formatDateTime(version.created_at)
                    }}</span>
                    <VersionKindBadge :kind="version.kind" />
                    <Badge
                        v-if="version.version_number === latestVersion"
                        variant="secondary"
                        >最新</Badge
                    >
                </div>
                <p
                    v-if="version.summary"
                    class="mt-0.5 truncate text-xs text-muted-foreground"
                >
                    {{ version.summary }}
                </p>
            </div>

            <div class="flex items-center gap-1">
                <Button
                    v-if="version.diffUrl"
                    variant="ghost"
                    size="sm"
                    class="h-7 px-2 text-xs"
                    as-child
                >
                    <Link :href="version.diffUrl">差分</Link>
                </Button>
                <RestoreVersionButton
                    v-if="
                        canRestore &&
                        version.kind === 'edit' &&
                        version.version_number !== latestVersion
                    "
                    :article-id="article.id"
                    :version-number="version.version_number"
                />
            </div>
        </li>
    </ul>

    <Pagination :paginator="versions" />
</template>
