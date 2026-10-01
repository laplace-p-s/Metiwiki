<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { VersionKind } from '@/types';

// 削除・復元の版の印（編集の版には何も出さない）
defineProps<{
    kind: VersionKind;
}>();
</script>

<template>
    <Badge v-if="kind === 'delete'" variant="destructive">削除</Badge>
    <Badge v-else-if="kind === 'restore'" variant="outline">復元</Badge>
</template>
