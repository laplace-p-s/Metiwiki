<script setup lang="ts">
import type { ToolbarItem } from '@/composables/useMarkdownToolbar';

defineProps<{
    items: ToolbarItem[];
}>();
</script>

<template>
    <div
        role="toolbar"
        aria-label="書式"
        class="flex flex-wrap items-center gap-0.5 border-b bg-muted/40 px-1.5 py-1"
    >
        <template v-for="(item, i) in items" :key="i">
            <span
                v-if="item.divider"
                class="mx-1 h-4 w-px bg-border"
                aria-hidden="true"
            />
            <button
                type="button"
                tabindex="-1"
                :title="item.title"
                :aria-label="item.title"
                class="rounded p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                @click="item.action"
            >
                <component :is="item.icon" class="size-4" />
            </button>
        </template>
    </div>
</template>
