<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconEye, IconHistory, IconPencil } from '@tabler/icons-vue';

// ページの閲覧・編集・履歴を切り替えるタブ。URL はサーバーが渡したものを使う
// （タイトルを含む URL は % などを確実にエンコードするためサーバーで作る）
defineProps<{
    active: 'view' | 'edit' | 'history';
    urls: {
        view: string;
        edit?: string | null;
        history?: string | null;
    };
}>();
</script>

<template>
    <div class="mb-4 flex items-center gap-1 border-b">
        <Link
            :href="urls.view"
            class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition-colors"
            :class="
                active === 'view'
                    ? 'border-foreground font-medium'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            <IconEye class="size-4" />
            閲覧
        </Link>
        <Link
            v-if="urls.edit"
            :href="urls.edit"
            class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition-colors"
            :class="
                active === 'edit'
                    ? 'border-foreground font-medium'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            <IconPencil class="size-4" />
            編集
        </Link>
        <Link
            v-if="urls.history"
            :href="urls.history"
            class="-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm transition-colors"
            :class="
                active === 'history'
                    ? 'border-foreground font-medium'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            <IconHistory class="size-4" />
            履歴
        </Link>
        <div class="ml-auto flex items-center gap-2 pb-1.5">
            <slot name="actions" />
        </div>
    </div>
</template>
