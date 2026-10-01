<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    paginator: Paginated<unknown>;
}>();
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        aria-label="ページ送り"
        class="mt-4 flex items-center justify-center gap-3 text-sm"
    >
        <Button
            variant="outline"
            size="sm"
            :disabled="!paginator.prev_page_url"
            :as-child="!!paginator.prev_page_url"
        >
            <Link
                v-if="paginator.prev_page_url"
                :href="paginator.prev_page_url"
                preserve-scroll
            >
                <IconChevronLeft class="size-4" />前へ
            </Link>
            <template v-else><IconChevronLeft class="size-4" />前へ</template>
        </Button>
        <span class="text-muted-foreground tabular-nums"
            >{{ paginator.current_page }} / {{ paginator.last_page }}</span
        >
        <Button
            variant="outline"
            size="sm"
            :disabled="!paginator.next_page_url"
            :as-child="!!paginator.next_page_url"
        >
            <Link
                v-if="paginator.next_page_url"
                :href="paginator.next_page_url"
            >
                次へ<IconChevronRight class="size-4" />
            </Link>
            <template v-else>次へ<IconChevronRight class="size-4" /></template>
        </Button>
    </nav>
</template>
