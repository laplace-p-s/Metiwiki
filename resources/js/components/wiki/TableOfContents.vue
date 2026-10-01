<script setup lang="ts">
import { IconLayoutSidebarRightCollapse } from '@tabler/icons-vue';
import type { TocItem } from '@/types';

defineProps<{
    items: TocItem[];
    // 折りたたみボタンを出す（閲覧画面の横に置くときだけ）
    collapsible?: boolean;
}>();

const emit = defineEmits<{ collapse: [] }>();

// 見出しレベルに応じた字下げ（最も浅いレベルを基準にする）
function indent(items: TocItem[], level: number): string {
    const base = Math.min(...items.map((i) => i.level));
    const depth = Math.min(level - base, 3);

    return ['', 'pl-3', 'pl-6', 'pl-9'][depth];
}
</script>

<template>
    <nav
        aria-label="目次"
        class="rounded-lg border bg-card p-4 text-sm shadow-xs"
    >
        <div class="mb-2 flex items-center justify-between gap-2">
            <span class="text-xs font-semibold text-muted-foreground"
                >目次</span
            >
            <button
                v-if="collapsible"
                type="button"
                class="-my-1 -mr-1 rounded p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                title="目次を折りたたむ"
                data-test="toc-collapse"
                @click="emit('collapse')"
            >
                <IconLayoutSidebarRightCollapse class="size-4" />
                <span class="sr-only">目次を折りたたむ</span>
            </button>
        </div>
        <ul class="space-y-1">
            <li v-for="item in items" :key="item.id">
                <a
                    :href="`#${item.id}`"
                    class="block truncate text-muted-foreground transition-colors hover:text-foreground"
                    :class="indent(items, item.level)"
                    :title="item.text"
                    >{{ item.text }}</a
                >
            </li>
        </ul>
    </nav>
</template>
