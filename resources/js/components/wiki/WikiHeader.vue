<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    IconChevronDown,
    IconHelpCircle,
    IconHistory,
    IconHome,
    IconList,
    IconLogin,
    IconMenu2,
    IconShieldCheck,
    IconUser,
} from '@tabler/icons-vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import NewPageDialog from '@/components/wiki/NewPageDialog.vue';
import WikiSearchForm from '@/components/wiki/WikiSearchForm.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home, login, register } from '@/routes';
import { allPages, help, recentChanges } from '@/routes/wiki';
import type { NavItem } from '@/types';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isCurrentUrl } = useCurrentUrl();

const navItems: NavItem[] = [
    { title: 'ホーム', href: home(), icon: IconHome },
    { title: '最近の更新', href: recentChanges(), icon: IconHistory },
    { title: '全ページ', href: allPages(), icon: IconList },
    { title: 'ヘルプ', href: help(), icon: IconHelpCircle },
];

const menuOpen = ref(false);
</script>

<template>
    <!--
      ヘッダーはテーマ色（サイト設定）で塗る。色は app.css の --header 系。
      画面上部に固定する。高さ（h-14）を変えるときは app.css の --header-height も合わせる
    -->
    <header
        class="sticky top-0 z-40 border-b border-header-border bg-header text-header-foreground"
    >
        <div class="mx-auto flex h-14 max-w-6xl items-center gap-2 px-4">
            <!--
              サイト内の移動（ホーム・最近の更新・全ページ・ヘルプ）は、ヘッダーに並べず
              左端のメニューボタンから開くドロワーにまとめる。狭い画面では検索欄もここに入れる
            -->
            <Sheet v-model:open="menuOpen">
                <SheetTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="-ml-2 hover:bg-header-accent hover:text-header-foreground"
                        data-test="site-menu"
                    >
                        <IconMenu2 class="size-5" />
                        <span class="sr-only">メニュー</span>
                    </Button>
                </SheetTrigger>
                <SheetContent side="left" class="w-72 gap-3 p-4">
                    <SheetHeader class="p-0">
                        <SheetTitle class="text-left">{{
                            page.props.name
                        }}</SheetTitle>
                        <SheetDescription class="sr-only"
                            >サイト内のメニュー</SheetDescription
                        >
                    </SheetHeader>
                    <WikiSearchForm
                        class="md:hidden"
                        @submitted="menuOpen = false"
                    />
                    <nav class="flex flex-col gap-1" aria-label="サイト内">
                        <Link
                            v-for="item in navItems"
                            :key="item.title"
                            :href="item.href"
                            class="flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors hover:bg-accent"
                            :class="{
                                'bg-accent font-medium': isCurrentUrl(
                                    item.href,
                                ),
                            }"
                            @click="menuOpen = false"
                        >
                            <component
                                :is="item.icon"
                                class="size-4 text-muted-foreground"
                            />
                            {{ item.title }}
                        </Link>
                    </nav>
                </SheetContent>
            </Sheet>

            <Link
                :href="home()"
                class="truncate text-base font-semibold hover:opacity-80"
            >
                {{ page.props.name }}
            </Link>

            <div class="ml-auto flex items-center gap-2">
                <WikiSearchForm class="hidden w-56 md:block" on-header />

                <template v-if="user">
                    <NewPageDialog />

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <!--
                              アイコン画像は設定できないため、アバターではなく名前を出す
                              （狭い画面では人型のアイコンだけ）
                            -->
                            <Button
                                variant="ghost"
                                size="sm"
                                class="max-w-44 gap-1.5 px-2 hover:bg-header-accent hover:text-header-foreground"
                                data-test="user-menu"
                            >
                                <IconUser class="size-4 shrink-0" />
                                <span class="hidden truncate sm:inline">{{
                                    user.name
                                }}</span>
                                <!-- 管理者の目印 -->
                                <IconShieldCheck
                                    v-if="user.is_admin"
                                    class="size-4 shrink-0 text-primary"
                                    aria-label="管理者"
                                    data-test="admin-badge"
                                />
                                <IconChevronDown
                                    class="size-3.5 shrink-0 text-header-muted"
                                />
                                <span class="sr-only">ユーザーメニュー</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>

                <template v-else>
                    <Button
                        v-if="page.props.canRegister"
                        variant="ghost"
                        size="sm"
                        class="hover:bg-header-accent hover:text-header-foreground"
                        as-child
                    >
                        <Link :href="register()">アカウント登録</Link>
                    </Button>
                    <Button
                        size="sm"
                        class="bg-header-button text-header-button-foreground hover:bg-header-button-hover"
                        as-child
                    >
                        <Link
                            :href="login({ query: { redirect: page.url } })"
                            class="gap-1.5"
                        >
                            <IconLogin class="size-4" />
                            ログイン
                        </Link>
                    </Button>
                </template>
            </div>
        </div>
    </header>
</template>
