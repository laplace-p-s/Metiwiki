<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { IconSearch } from '@tabler/icons-vue';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { search } from '@/routes/wiki';

// onHeader: ヘッダーの上に置くとき、入力欄の地と文字をヘッダー用の色（app.css の --header 系）にする
defineProps<{ onHeader?: boolean }>();

const emit = defineEmits<{ submitted: [] }>();

const page = usePage();

// 検索結果ページでは、実行中の検索語を入力欄に残す
function currentQuery(): string {
    const url = new URL(page.url, 'http://localhost');

    return url.pathname === search.url()
        ? (url.searchParams.get('q') ?? '')
        : '';
}

const q = ref(currentQuery());
watch(
    () => page.url,
    () => (q.value = currentQuery()),
);

function submit() {
    if (!q.value.trim()) {
        return;
    }

    router.get(search.url(), { q: q.value });
    emit('submitted');
}
</script>

<template>
    <form role="search" class="relative" @submit.prevent="submit">
        <IconSearch
            class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
            :class="onHeader ? 'text-header-muted' : 'text-muted-foreground'"
        />
        <Input
            v-model="q"
            type="search"
            placeholder="Wiki 内を検索"
            aria-label="Wiki 内を検索"
            class="h-9 pl-8"
            :class="{
                'border-header-border bg-header-field text-header-foreground shadow-none placeholder:text-header-muted':
                    onHeader,
            }"
        />
    </form>
</template>
