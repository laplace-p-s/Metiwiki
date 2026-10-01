<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
</script>

<template>
    <Head title="外観の設定" />

    <h1 class="sr-only">外観の設定</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="外観"
            description="ライト・ダークの表示を切り替えます（この端末のブラウザに保存されます）"
        />
        <AppearanceTabs />
    </div>
</template>
