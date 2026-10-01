<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/wiki/Pagination.vue';
import { formatDateTime } from '@/lib/datetime';
import { destroy } from '@/routes/admin/uploads';
import type { Paginated } from '@/types';

type UploadRow = {
    id: number;
    name: string;
    url: string;
    mime: string;
    size: number;
    created_at: string | null;
    user: string | null;
    // 最新版の本文でこの画像を使っているページの数と、その先頭の数件
    usageCount: number;
    usedIn: { title: string; url: string }[];
};

defineProps<{
    uploads: Paginated<UploadRow>;
    totalSize: number;
}>();

function formatSize(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))}KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)}MB`;
}

const deleting = ref<number | null>(null);

function deleteUpload(upload: UploadRow) {
    const message =
        upload.usageCount > 0
            ? `「${upload.name}」は ${upload.usageCount} ページで使われています。削除すると、そのページでは画像が表示されなくなります。削除しますか？`
            : `「${upload.name}」を削除しますか？`;

    if (!window.confirm(message)) {
        return;
    }

    router.delete(destroy.url(upload.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = upload.id),
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="画像" />

    <Heading
        variant="small"
        title="画像"
        :description="`${uploads.total} 枚・合計 ${formatSize(totalSize)}。画像は自動では削除されません。使われていない画像はここから削除できます。使用数は各ページの最新版の本文で数えるため、古い版だけで使っている画像を削除すると、その版では表示されなくなります`"
    />

    <p
        v-if="uploads.data.length === 0"
        class="mt-4 rounded-lg border bg-card px-4 py-8 text-center text-sm text-muted-foreground shadow-xs"
    >
        アップロードされた画像はありません。
    </p>
    <div
        v-else
        class="mt-4 overflow-x-auto rounded-lg border bg-card shadow-xs"
    >
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-muted/50 text-left">
                    <th class="px-4 py-2 font-medium">画像</th>
                    <th class="px-4 py-2 font-medium">使用</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">
                        アップロード
                    </th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr
                    v-for="upload in uploads.data"
                    :key="upload.id"
                    data-test="upload"
                >
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                            <a
                                :href="upload.url"
                                target="_blank"
                                rel="noopener"
                                class="shrink-0"
                            >
                                <img
                                    :src="upload.url"
                                    :alt="upload.name"
                                    loading="lazy"
                                    class="size-12 rounded border bg-muted object-contain"
                                />
                            </a>
                            <div class="min-w-0">
                                <a
                                    :href="upload.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="break-all underline-offset-2 hover:underline"
                                    >{{ upload.name }}</a
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ formatSize(upload.size) }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        <Badge
                            v-if="upload.usageCount === 0"
                            variant="secondary"
                            >未使用</Badge
                        >
                        <template v-else>
                            <ul class="space-y-0.5">
                                <li
                                    v-for="page in upload.usedIn"
                                    :key="page.url"
                                    class="break-words"
                                >
                                    <Link
                                        :href="page.url"
                                        class="underline-offset-2 hover:underline"
                                        >{{ page.title }}</Link
                                    >
                                </li>
                            </ul>
                            <p
                                v-if="upload.usageCount > upload.usedIn.length"
                                class="text-xs text-muted-foreground"
                            >
                                ほか
                                {{ upload.usageCount - upload.usedIn.length }}
                                ページ
                            </p>
                        </template>
                    </td>
                    <td
                        class="hidden px-4 py-2 text-muted-foreground md:table-cell"
                    >
                        {{ formatDateTime(upload.created_at) }}
                        <template v-if="upload.user"
                            >（{{ upload.user }}）</template
                        >
                    </td>
                    <td class="px-4 py-2 text-right">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 text-destructive hover:text-destructive"
                            :disabled="deleting === upload.id"
                            @click="deleteUpload(upload)"
                            >削除</Button
                        >
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :paginator="uploads" />
</template>
