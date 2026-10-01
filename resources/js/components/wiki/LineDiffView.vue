<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { computeLineDiff, diffStats, toSideBySideRows } from '@/lib/lineDiff';

const props = defineProps<{
    oldText: string;
    newText: string;
}>();

// 統合表示 / 左右比較の選択はブラウザに記憶する
const VIEW_STORAGE_KEY = 'metiwiki.diff.view';

function storedView(): 'unified' | 'split' {
    try {
        return localStorage.getItem(VIEW_STORAGE_KEY) === 'split'
            ? 'split'
            : 'unified';
    } catch {
        return 'unified';
    }
}

const viewMode = ref(storedView());

watch(viewMode, (mode) => {
    try {
        localStorage.setItem(VIEW_STORAGE_KEY, mode);
    } catch {
        // 保存できない環境（プライベートブラウズなど）では記憶しない
    }
});

const lines = computed(() => computeLineDiff(props.oldText, props.newText));
const rows = computed(() => toSideBySideRows(lines.value));
const stats = computed(() => diffStats(lines.value));
const hasChanges = computed(() => stats.value.added + stats.value.deleted > 0);

const modes = [
    { key: 'unified', label: '統合' },
    { key: 'split', label: '左右' },
] as const;
</script>

<template>
    <div class="overflow-hidden rounded-lg border bg-card shadow-xs">
        <div
            class="flex items-center gap-3 border-b bg-muted/50 px-4 py-2 text-xs text-muted-foreground"
        >
            <span class="text-green-700 tabular-nums dark:text-green-400"
                >+{{ stats.added }}</span
            >
            <span class="text-red-700 tabular-nums dark:text-red-400"
                >-{{ stats.deleted }}</span
            >
            <span
                class="ml-auto inline-flex overflow-hidden rounded border text-[11px]"
                role="group"
                aria-label="表示方法"
            >
                <button
                    v-for="mode in modes"
                    :key="mode.key"
                    type="button"
                    class="px-2 py-0.5 transition-colors"
                    :class="
                        viewMode === mode.key
                            ? 'bg-accent font-medium text-foreground'
                            : 'bg-background hover:text-foreground'
                    "
                    :aria-pressed="viewMode === mode.key"
                    @click="viewMode = mode.key"
                >
                    {{ mode.label }}
                </button>
            </span>
        </div>

        <p v-if="!hasChanges" class="px-4 py-6 text-sm text-muted-foreground">
            本文に変更はありません。
        </p>

        <!-- 統合表示。行番号は変更前・変更後の 2 列（その側に無い行は空欄） -->
        <div
            v-else-if="viewMode === 'unified'"
            class="overflow-x-auto font-mono text-xs"
            data-test="diff-unified"
        >
            <div
                v-for="(line, i) in lines"
                :key="i"
                class="flex leading-5"
                :class="{
                    'bg-green-50 text-green-900 dark:bg-green-500/10 dark:text-green-200':
                        line.type === 'add',
                    'bg-red-50 text-red-900 dark:bg-red-500/10 dark:text-red-200':
                        line.type === 'del',
                    'text-muted-foreground': line.type === 'same',
                }"
            >
                <span
                    v-for="(no, ni) in [line.oldNo, line.newNo]"
                    :key="ni"
                    class="w-10 shrink-0 pr-1.5 text-right tabular-nums opacity-60 select-none"
                    >{{ no ?? '' }}</span
                >
                <span class="w-5 shrink-0 text-center select-none">{{
                    line.type === 'add' ? '+' : line.type === 'del' ? '-' : ' '
                }}</span>
                <span class="flex-1 px-2 break-all whitespace-pre-wrap">{{
                    line.text
                }}</span>
            </div>
        </div>

        <!-- 左右比較。行番号は各側の本文における番号 -->
        <div v-else class="font-mono text-xs" data-test="diff-split">
            <div
                class="grid grid-cols-2 border-b bg-muted/50 text-[10px] text-muted-foreground"
            >
                <div class="border-r py-0.5 pr-3 pl-12">変更前</div>
                <div class="py-0.5 pr-3 pl-12">変更後</div>
            </div>
            <div
                v-for="(row, i) in rows"
                :key="i"
                class="grid grid-cols-2 leading-5"
            >
                <div
                    class="flex border-r"
                    :class="
                        row.left === null
                            ? 'bg-muted/40'
                            : row.type === 'same'
                              ? 'text-muted-foreground'
                              : 'bg-red-50 text-red-900 dark:bg-red-500/10 dark:text-red-200'
                    "
                >
                    <span
                        class="w-10 shrink-0 pr-1.5 text-right tabular-nums opacity-60 select-none"
                        >{{ row.leftNo ?? '' }}</span
                    >
                    <span class="flex-1 px-2 break-all whitespace-pre-wrap">{{
                        row.left
                    }}</span>
                </div>
                <div
                    class="flex"
                    :class="
                        row.right === null
                            ? 'bg-muted/40'
                            : row.type === 'same'
                              ? 'text-muted-foreground'
                              : 'bg-green-50 text-green-900 dark:bg-green-500/10 dark:text-green-200'
                    "
                >
                    <span
                        class="w-10 shrink-0 pr-1.5 text-right tabular-nums opacity-60 select-none"
                        >{{ row.rightNo ?? '' }}</span
                    >
                    <span class="flex-1 px-2 break-all whitespace-pre-wrap">{{
                        row.right
                    }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
