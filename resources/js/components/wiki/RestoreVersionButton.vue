<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconRestore } from '@tabler/icons-vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { restoreVersion } from '@/routes/wiki';

const props = defineProps<{
    articleId: number;
    versionNumber: number;
}>();

const processing = ref(false);

function restore() {
    router.post(
        restoreVersion.url([props.articleId, props.versionNumber]),
        {},
        {
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="ghost" size="sm" class="h-7 gap-1 px-2 text-xs">
                <IconRestore class="size-3.5" />
                この版に戻す
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle
                    >v{{ versionNumber }} の内容に戻しますか？</DialogTitle
                >
                <DialogDescription>
                    選んだ版の本文を新しい版として保存します。今までの版は履歴に残ります。
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">キャンセル</Button>
                </DialogClose>
                <Button :disabled="processing" @click="restore">戻す</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
