<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Pagination from '@/components/wiki/Pagination.vue';
import VersionKindBadge from '@/components/wiki/VersionKindBadge.vue';
import { formatDate } from '@/lib/datetime';
import type { ArticleSummary, Paginated, VersionSummary } from '@/types';

type RecentVersion = VersionSummary & {
    article: ArticleSummary;
    // 現在は削除されているページ（削除・復元の行だけが載る）
    isDeleted: boolean;
    diffUrl: string | null;
};

const props = defineProps<{
    versions: Paginated<RecentVersion>;
}>();

const timeFormat = new Intl.DateTimeFormat('ja-JP', {
    hour: '2-digit',
    minute: '2-digit',
});

// 日付ごとにまとめる（並びは新しい順のまま）
const groups = computed(() => {
    const map = new Map<string, RecentVersion[]>();

    for (const version of props.versions.data) {
        const day = formatDate(version.created_at);
        map.set(day, [...(map.get(day) ?? []), version]);
    }

    return [...map.entries()].map(([day, items]) => ({ day, items }));
});
</script>

<template>
    <Head title="最近の更新" />

    <h1 class="mb-4 text-xl font-semibold">最近の更新</h1>

    <p
        v-if="versions.data.length === 0"
        class="rounded-lg border bg-card px-4 py-12 text-center text-sm text-muted-foreground shadow-xs"
    >
        まだ更新がありません。
    </p>

    <div v-else class="space-y-6">
        <section v-for="group in groups" :key="group.day">
            <h2 class="mb-2 text-sm font-semibold text-muted-foreground">
                {{ group.day }}
            </h2>
            <ul class="divide-y rounded-lg border bg-card shadow-xs">
                <li
                    v-for="version in group.items"
                    :key="`${version.article.id}-${version.version_number}`"
                    class="flex flex-wrap items-baseline gap-x-3 gap-y-1 px-4 py-2.5 text-sm"
                >
                    <span
                        class="w-11 shrink-0 text-xs text-muted-foreground tabular-nums"
                        >{{
                            version.created_at
                                ? timeFormat.format(
                                      new Date(version.created_at),
                                  )
                                : ''
                        }}</span
                    >
                    <Link
                        :href="version.article.url"
                        class="font-medium break-all hover:underline"
                        :class="{
                            'text-muted-foreground line-through':
                                version.isDeleted,
                        }"
                        >{{ version.article.title }}</Link
                    >
                    <span class="font-mono text-xs text-muted-foreground"
                        >v{{ version.version_number }}</span
                    >
                    <VersionKindBadge
                        v-if="version.kind !== 'edit'"
                        :kind="version.kind"
                    />
                    <Link
                        v-else-if="version.diffUrl"
                        :href="version.diffUrl"
                        class="text-xs text-muted-foreground underline-offset-2 hover:text-foreground hover:underline"
                        >差分</Link
                    >
                    <span
                        v-else-if="version.version_number === 1"
                        class="text-xs text-muted-foreground"
                        >新規作成</span
                    >
                    <span class="text-xs text-muted-foreground">{{
                        version.user?.name ?? '削除されたユーザー'
                    }}</span>
                    <span
                        v-if="version.summary"
                        class="w-full truncate pl-14 text-xs text-muted-foreground"
                        >{{ version.summary }}</span
                    >
                </li>
            </ul>
        </section>
    </div>

    <Pagination :paginator="versions" />
</template>
