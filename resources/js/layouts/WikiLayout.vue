<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { IconBrandGithub } from '@tabler/icons-vue';
import { Toaster } from '@/components/ui/sonner';
import WikiHeader from '@/components/wiki/WikiHeader.vue';

const page = usePage();
</script>

<template>
    <div class="flex min-h-svh flex-col bg-background text-foreground">
        <WikiHeader />

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
            <slot />
        </main>

        <footer class="border-t bg-muted">
            <div
                class="mx-auto max-w-6xl px-4 py-4 text-xs text-muted-foreground"
            >
                Powered by
                <a
                    href="https://github.com/laplace-p-s/Metiwiki"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-0.5 align-bottom font-medium text-(--wiki-link) underline-offset-2 hover:underline"
                >
                    <IconBrandGithub class="size-3.5" aria-hidden="true" />
                    Metiwiki
                </a>
                <!-- バージョンは管理者にだけ渡される -->
                <span v-if="page.props.appVersion" class="ml-1.5">
                    {{ page.props.appVersion }}
                </span>
            </div>
        </footer>

        <Toaster />
    </div>
</template>
