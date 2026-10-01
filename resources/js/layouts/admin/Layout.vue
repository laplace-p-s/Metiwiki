<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconPhoto,
    IconSettings,
    IconTicket,
    IconTrash,
    IconUsers,
} from '@tabler/icons-vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { index as deletedPagesIndex } from '@/routes/admin/deleted-pages';
import { index as invitationsIndex } from '@/routes/admin/invitations';
import { edit as editSettings } from '@/routes/admin/settings';
import { index as uploadsIndex } from '@/routes/admin/uploads';
import { index as usersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'ユーザー', href: usersIndex(), icon: IconUsers },
    { title: '招待リンク', href: invitationsIndex(), icon: IconTicket },
    { title: '削除済みページ', href: deletedPagesIndex(), icon: IconTrash },
    { title: '画像', href: uploadsIndex(), icon: IconPhoto },
    { title: 'サイト設定', href: editSettings(), icon: IconSettings },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
const page = usePage();
</script>

<template>
    <div>
        <Heading
            title="管理"
            description="ユーザー・削除済みページ・画像の管理と Wiki 全体の設定を行います"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-y-1" aria-label="管理">
                    <Button
                        v-for="item in navItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>

                <p
                    v-if="page.props.appVersion"
                    class="mt-4 px-3 text-xs text-muted-foreground"
                >
                    Metiwiki {{ page.props.appVersion }}
                </p>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1">
                <slot />
            </div>
        </div>
    </div>
</template>
