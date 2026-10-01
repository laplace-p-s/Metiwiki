<script setup lang="ts">
import { router } from '@inertiajs/vue3';

// html はサーバー（league/commonmark）で描画済み。生の HTML はエスケープされ、
// 危険なリンクは除かれているため、そのまま v-html で表示する
defineProps<{
    html: string;
}>();

// 本文中のサイト内リンク（WikiLink など）は Inertia の遷移にして、ページ全体の再読み込みを避ける
function onClick(event: MouseEvent) {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return;
    }

    const anchor = (event.target as HTMLElement).closest('a');
    const href = anchor?.getAttribute('href');

    if (!anchor || !href || anchor.target === '_blank') {
        return;
    }

    // 同じページ内の見出しへのリンク（#h-...）や外部リンクはブラウザに任せる
    if (!href.startsWith('/') || href.startsWith('//')) {
        return;
    }

    event.preventDefault();
    router.visit(href);
}
</script>

<template>
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="wiki-body" @click="onClick" v-html="html" />
</template>
