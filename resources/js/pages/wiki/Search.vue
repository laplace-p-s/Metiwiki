<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { IconFilePlus } from '@tabler/icons-vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/datetime';

type SearchResult = {
    id: number;
    title: string;
    snippet: string;
    updated_at: string | null;
    url: string;
};

const props = defineProps<{
    query: string;
    results: SearchResult[];
    // 検索語と同じタイトルのページがあるか
    exactMatch: boolean;
    // 検索語をタイトルとして作成できるときの編集画面の URL
    createUrl: string | null;
}>();

const page = usePage();
const canCreate = computed(
    () => !!page.props.auth.user && !props.exactMatch && !!props.createUrl,
);

// 検索語（半角・全角スペース区切り）。ヒット箇所の強調に使う
const terms = computed(() =>
    props.query.split(/[\s　]+/).filter((t) => t !== ''),
);

function escapeRegExp(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// テキストを検索語の一致部分とそれ以外に分ける（v-html を使わずに強調するため）
function segments(text: string): { text: string; hit: boolean }[] {
    if (!terms.value.length || !text) {
        return [{ text, hit: false }];
    }

    const pattern = new RegExp(
        `(${terms.value.map(escapeRegExp).join('|')})`,
        'i',
    );

    // キャプチャ付きの split は「一致しない部分, 一致した部分, 一致しない部分, …」の順に並ぶ
    return text
        .split(pattern)
        .map((part, i) => ({ text: part, hit: i % 2 === 1 }))
        .filter((seg) => seg.text !== '');
}
</script>

<template>
    <Head :title="query ? `「${query}」の検索結果` : '検索'" />

    <div class="mb-4 flex flex-wrap items-baseline gap-3">
        <h1 class="text-xl font-semibold">検索結果</h1>
        <span v-if="query" class="text-sm text-muted-foreground"
            >「{{ query }}」— {{ results.length }} 件</span
        >
    </div>

    <div
        v-if="canCreate"
        class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border bg-card px-4 py-3 text-sm shadow-xs"
    >
        <span>「{{ query }}」というページはまだありません。</span>
        <Button size="sm" variant="outline" as-child>
            <Link :href="createUrl!" class="gap-1.5">
                <IconFilePlus class="size-4" />
                このタイトルで作成する
            </Link>
        </Button>
    </div>

    <p
        v-if="!query"
        class="rounded-lg border bg-card px-4 py-12 text-center text-sm text-muted-foreground shadow-xs"
    >
        ヘッダーの検索欄にキーワードを入力してください。空白で区切ると、すべての語を含むページを探します。
    </p>

    <p
        v-else-if="results.length === 0"
        class="rounded-lg border bg-card px-4 py-12 text-center text-sm text-muted-foreground shadow-xs"
    >
        「{{ query }}」に一致するページは見つかりませんでした。
    </p>

    <ul v-else class="divide-y rounded-lg border bg-card shadow-xs">
        <li v-for="result in results" :key="result.id">
            <Link
                :href="result.url"
                class="block px-4 py-3 transition-colors hover:bg-accent"
            >
                <div class="flex flex-wrap items-baseline gap-x-3">
                    <span class="font-medium break-all">
                        <template
                            v-for="(seg, i) in segments(result.title)"
                            :key="i"
                            ><mark
                                v-if="seg.hit"
                                class="rounded bg-yellow-200 px-0.5 text-inherit dark:bg-yellow-500/40"
                                >{{ seg.text }}</mark
                            ><template v-else>{{
                                seg.text
                            }}</template></template
                        >
                    </span>
                    <span class="text-xs text-muted-foreground">{{
                        formatDateTime(result.updated_at)
                    }}</span>
                </div>
                <p
                    v-if="result.snippet"
                    class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                >
                    <template
                        v-for="(seg, i) in segments(result.snippet)"
                        :key="i"
                        ><mark
                            v-if="seg.hit"
                            class="rounded bg-yellow-200 px-0.5 text-inherit dark:bg-yellow-500/40"
                            >{{ seg.text }}</mark
                        ><template v-else>{{ seg.text }}</template></template
                    >
                </p>
            </Link>
        </li>
    </ul>
</template>
