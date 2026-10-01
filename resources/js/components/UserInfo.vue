<script setup lang="ts">
import { IconShieldCheck, IconUser } from '@tabler/icons-vue';
import type { User } from '@/types';

// アイコン画像は設定できないため、アバターではなく人型のアイコンと名前を出す
withDefaults(
    defineProps<{
        user: User;
        showEmail?: boolean;
    }>(),
    {
        showEmail: false,
    },
);
</script>

<template>
    <div
        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
    >
        <IconUser class="size-4" />
    </div>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="flex min-w-0 items-center gap-1 font-medium">
            <span class="truncate">{{ user.name }}</span>
            <span
                v-if="user.is_admin"
                class="inline-flex shrink-0 items-center gap-0.5 text-xs font-normal text-primary"
            >
                <IconShieldCheck class="size-3.5" />
                管理者
            </span>
        </span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email ?? user.login_id
        }}</span>
    </div>
</template>
