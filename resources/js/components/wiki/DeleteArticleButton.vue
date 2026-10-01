<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconTrash } from '@tabler/icons-vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/wiki';

// ページの削除（確認ダイアログ付き）。編集タブに置く
const props = defineProps<{
    articleId: number;
}>();

// 削除の理由（任意）。削除の版の要約として履歴・最近の更新に残る
const form = useForm({ summary: '' });

function deletePage() {
    form.delete(destroy.url(props.articleId));
}
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                data-test="delete-article"
            >
                <IconTrash class="size-4" />
                <span class="sr-only sm:not-sr-only">削除</span>
            </Button>
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>ページを削除しますか？</DialogTitle>
                <DialogDescription>
                    このページを削除します。削除したページは管理者が復元できます。同じタイトルのページを新しく作ることもできます。
                </DialogDescription>
            </DialogHeader>
            <form class="grid gap-2" @submit.prevent="deletePage">
                <Label for="delete-summary">削除の理由（任意）</Label>
                <Input
                    id="delete-summary"
                    v-model="form.summary"
                    maxlength="255"
                    placeholder="履歴・最近の更新に表示されます"
                    autocomplete="off"
                />
                <InputError :message="form.errors.summary" />
            </form>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">キャンセル</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="form.processing"
                    @click="deletePage"
                    >削除する</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
