<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconFileText } from '@tabler/icons-vue';
import { computed } from 'vue';
import type { ArticleSummary } from '@/types';

const props = defineProps<{
    // サーバーで照合キー（大文字小文字を無視）の順に並べ済み
    pages: (ArticleSummary & { updated_at: string | null })[];
}>();

// 先頭の文字ごとにまとめた索引（英字は大文字でまとめる）
const groups = computed(() => {
    const map = new Map<string, typeof props.pages>();

    for (const page of props.pages) {
        const first = [...page.title][0]?.toUpperCase() ?? '#';
        map.set(first, [...(map.get(first) ?? []), page]);
    }

    return [...map.entries()].map(([letter, items]) => ({ letter, items }));
});
</script>

<template>
    <Head title="全ページ" />

    <div class="mb-4 flex items-baseline gap-3">
        <h1 class="text-xl font-semibold">全ページ</h1>
        <span class="text-sm text-muted-foreground"
            >{{ pages.length }} ページ</span
        >
    </div>

    <p
        v-if="pages.length === 0"
        class="rounded-lg border bg-card px-4 py-12 text-center text-sm text-muted-foreground shadow-xs"
    >
        ページがありません。
    </p>

    <div v-else class="divide-y rounded-lg border bg-card shadow-xs">
        <section v-for="group in groups" :key="group.letter" class="px-4 py-3">
            <h2 class="mb-2 text-xs font-bold text-muted-foreground">
                {{ group.letter }}
            </h2>
            <ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="page in group.items" :key="page.id">
                    <Link
                        :href="page.url"
                        class="flex items-center gap-2 py-0.5 text-sm hover:underline"
                    >
                        <IconFileText
                            class="size-3.5 shrink-0 text-muted-foreground"
                        />
                        <span class="truncate">{{ page.title }}</span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
