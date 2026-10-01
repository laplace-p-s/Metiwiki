<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/wiki/Pagination.vue';
import { formatDateTime } from '@/lib/datetime';
import { restore } from '@/routes/admin/deleted-pages';
import type { Paginated } from '@/types';

type DeletedArticleRow = {
    id: number;
    title: string;
    deleted_at: string | null;
    deleter: string | null;
    // 削除の理由（削除の版の要約）
    reason: string | null;
    updated_at: string | null;
    updater: string | null;
    versionCount: number;
    // 削除後に同じタイトルで作られたページ（あると復元できない）
    takenBy: { title: string; url: string } | null;
};

defineProps<{
    articles: Paginated<DeletedArticleRow>;
}>();

const restoring = ref<number | null>(null);

function restorePage(article: DeletedArticleRow) {
    if (!window.confirm(`「${article.title}」を復元しますか？`)) {
        return;
    }

    router.post(
        restore.url(article.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (restoring.value = article.id),
            onFinish: () => (restoring.value = null),
        },
    );
}
</script>

<template>
    <Head title="削除済みページ" />

    <Heading
        variant="small"
        title="削除済みページ"
        description="削除したページは本文と履歴を残したまま、ここから元に戻せます。削除後に同じタイトルのページが作られている場合は、先にそのページを改名するか削除してください"
    />

    <p
        v-if="articles.data.length === 0"
        class="mt-4 rounded-lg border bg-card px-4 py-8 text-center text-sm text-muted-foreground shadow-xs"
    >
        削除済みのページはありません。
    </p>
    <div
        v-else
        class="mt-4 overflow-x-auto rounded-lg border bg-card shadow-xs"
    >
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-muted/50 text-left">
                    <th class="px-4 py-2 font-medium">タイトル</th>
                    <th class="px-4 py-2 font-medium">削除日時・削除者</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">
                        最終更新
                    </th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell">
                        版
                    </th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr
                    v-for="article in articles.data"
                    :key="article.id"
                    data-test="deleted-page"
                >
                    <td class="px-4 py-2 break-words">
                        <span class="font-medium">{{ article.title }}</span>
                        <p
                            v-if="article.reason"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            理由: {{ article.reason }}
                        </p>
                        <p
                            v-if="article.takenBy"
                            class="mt-0.5 text-xs text-muted-foreground"
                        >
                            同じタイトルの<Link
                                :href="article.takenBy.url"
                                class="underline underline-offset-2"
                                >「{{ article.takenBy.title }}」</Link
                            >があります
                        </p>
                    </td>
                    <td
                        class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                    >
                        {{ formatDateTime(article.deleted_at) }}
                        <p v-if="article.deleter" class="text-xs">
                            {{ article.deleter }}
                        </p>
                    </td>
                    <td
                        class="hidden px-4 py-2 text-muted-foreground md:table-cell"
                    >
                        {{ formatDateTime(article.updated_at) }}
                        <template v-if="article.updater"
                            >（{{ article.updater }}）</template
                        >
                    </td>
                    <td
                        class="hidden px-4 py-2 text-muted-foreground sm:table-cell"
                    >
                        {{ article.versionCount }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7"
                            :disabled="
                                article.takenBy !== null ||
                                restoring === article.id
                            "
                            @click="restorePage(article)"
                            >復元する</Button
                        >
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :paginator="articles" />
</template>
