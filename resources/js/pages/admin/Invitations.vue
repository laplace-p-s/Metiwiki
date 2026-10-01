<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { IconCheck, IconCopy } from '@tabler/icons-vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/wiki/Pagination.vue';
import { formatDateTime } from '@/lib/datetime';
import { destroy, store } from '@/routes/admin/invitations';
import type { Paginated } from '@/types';

type InvitationRow = {
    id: number;
    created_at: string | null;
    expires_at: string;
    used_at: string | null;
    creator: string | null;
    used_by: string | null;
    status: 'active' | 'used' | 'expired';
};

const props = defineProps<{
    invitations: Paginated<InvitationRow>;
    defaultDays: number;
}>();

const page = usePage();
// 発行した直後だけ URL を表示できる（トークンは保存していない）
const issuedUrl = computed(() => page.flash.invitationUrl);

const form = useForm({ days: props.defaultDays });

function issue() {
    form.post(store.url(), { preserveScroll: true });
}

const copied = ref(false);

async function copy() {
    if (!issuedUrl.value) {
        return;
    }

    await navigator.clipboard.writeText(issuedUrl.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function revoke(id: number) {
    if (window.confirm('この招待リンクを失効させますか？')) {
        router.delete(destroy.url(id), { preserveScroll: true });
    }
}

const statusLabels = {
    active: '有効',
    used: '使用済み',
    expired: '期限切れ',
} as const;
</script>

<template>
    <Head title="招待リンク" />

    <div class="space-y-8">
        <section class="space-y-4">
            <Heading
                variant="small"
                title="招待リンク"
                description="招待リンクを渡すと、アカウント登録を締め切っていても、その人が自分でアカウントを作れます。1 つのリンクで登録できるのは 1 人です"
            />

            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="issue"
            >
                <div class="grid gap-2">
                    <Label for="days">有効日数</Label>
                    <Input
                        id="days"
                        v-model.number="form.days"
                        type="number"
                        min="1"
                        max="90"
                        class="w-28"
                    />
                </div>
                <Button type="submit" :disabled="form.processing"
                    >招待リンクを発行する</Button
                >
            </form>
            <InputError :message="form.errors.days" />

            <div
                v-if="issuedUrl"
                class="space-y-2 rounded-lg border border-green-300 bg-green-50 p-4 text-sm dark:border-green-500/40 dark:bg-green-500/10"
                data-test="issued-invitation"
            >
                <p class="font-medium">招待リンクを発行しました</p>
                <p class="text-muted-foreground">
                    このリンクは今しか表示できません。コピーして、招待する人に伝えてください。
                </p>
                <div class="flex items-center gap-2">
                    <Input
                        :model-value="issuedUrl"
                        readonly
                        class="font-mono text-xs"
                        aria-label="招待リンク"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        @click="copy"
                    >
                        <IconCheck v-if="copied" class="size-4" />
                        <IconCopy v-else class="size-4" />
                        <span class="sr-only">コピー</span>
                    </Button>
                </div>
            </div>
        </section>

        <section>
            <p
                v-if="invitations.data.length === 0"
                class="rounded-lg border bg-card px-4 py-8 text-center text-sm text-muted-foreground shadow-xs"
            >
                発行した招待リンクはありません。
            </p>
            <div
                v-else
                class="overflow-x-auto rounded-lg border bg-card shadow-xs"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-2 font-medium">状態</th>
                            <th class="px-4 py-2 font-medium">発行</th>
                            <th class="px-4 py-2 font-medium">有効期限</th>
                            <th class="px-4 py-2 font-medium">登録した人</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="invitation in invitations.data"
                            :key="invitation.id"
                        >
                            <td class="px-4 py-2">
                                <Badge
                                    :variant="
                                        invitation.status === 'active'
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    >{{
                                        statusLabels[invitation.status]
                                    }}</Badge
                                >
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ formatDateTime(invitation.created_at) }}
                                <template v-if="invitation.creator"
                                    >（{{ invitation.creator }}）</template
                                >
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ formatDateTime(invitation.expires_at) }}
                            </td>
                            <td class="px-4 py-2">
                                {{ invitation.used_by ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                <Button
                                    v-if="invitation.status !== 'used'"
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 text-destructive hover:text-destructive"
                                    @click="revoke(invitation.id)"
                                    >失効させる</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="invitations" />
        </section>
    </div>
</template>
