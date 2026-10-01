<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { IconUserPlus } from '@tabler/icons-vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/wiki/Pagination.vue';
import { formatDate } from '@/lib/datetime';
import { create, edit } from '@/routes/admin/users';
import type { AdminUser, Paginated } from '@/types';

defineProps<{
    users: Paginated<
        AdminUser & { created_at: string | null; is_self: boolean }
    >;
}>();
</script>

<template>
    <Head title="ユーザー" />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <Heading
            variant="small"
            title="ユーザー"
            :description="`${users.total} 人が登録しています`"
        />
        <Button size="sm" as-child>
            <Link :href="create()" class="gap-1.5">
                <IconUserPlus class="size-4" />
                ユーザーを作成
            </Link>
        </Button>
    </div>

    <div class="overflow-x-auto rounded-lg border bg-card shadow-xs">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b bg-muted/50 text-left">
                    <th class="px-4 py-2 font-medium">ログイン ID</th>
                    <th class="px-4 py-2 font-medium">名前</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">
                        メールアドレス
                    </th>
                    <th class="px-4 py-2 font-medium">状態</th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell">
                        登録日
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y">
                <tr v-for="user in users.data" :key="user.id">
                    <td class="px-4 py-2 font-mono">
                        <Link
                            :href="edit(user.id)"
                            class="underline-offset-2 hover:underline"
                            >{{ user.login_id }}</Link
                        >
                    </td>
                    <td class="px-4 py-2">
                        {{ user.name }}
                        <span
                            v-if="user.is_self"
                            class="text-xs text-muted-foreground"
                            >（あなた）</span
                        >
                    </td>
                    <td
                        class="hidden px-4 py-2 text-muted-foreground md:table-cell"
                    >
                        {{ user.email ?? '—' }}
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex flex-wrap gap-1">
                            <Badge v-if="user.is_admin">管理者</Badge>
                            <Badge
                                v-if="user.two_factor_enabled"
                                variant="secondary"
                                >2 段階認証</Badge
                            >
                        </div>
                    </td>
                    <td
                        class="hidden px-4 py-2 text-muted-foreground sm:table-cell"
                    >
                        {{ formatDate(user.created_at) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :paginator="users" />
</template>
