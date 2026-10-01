<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconFilePlus } from '@tabler/icons-vue';
import { ref } from 'vue';
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
import { create } from '@/routes/wiki';

const open = ref(false);

// タイトルの正規化・検証と URL の組み立てはサーバー（/-/new）が行い、編集画面へリダイレクトする
const form = useForm({ title: '' });

function submit() {
    form.get(create.url(), {
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open" @update:open="(v) => !v && form.clearErrors()">
        <DialogTrigger as-child>
            <!-- テーマ色で塗ったヘッダーの上に置くため、ヘッダー用のボタン色にする（app.css の --header-button） -->
            <Button
                size="sm"
                class="gap-1.5 bg-header-button text-header-button-foreground hover:bg-header-button-hover"
            >
                <IconFilePlus class="size-4" />
                <!-- 狭い画面ではアイコンだけにする（読み上げ用の文字は残す） -->
                <span class="sr-only sm:not-sr-only">新規ページ</span>
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>新規ページを作成</DialogTitle>
                    <DialogDescription>
                        タイトルがそのままURLになります。次の画面で本文を入力します。
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="new-page-title">タイトル</Label>
                    <Input
                        id="new-page-title"
                        v-model="form.title"
                        placeholder="ページタイトル"
                        autocomplete="off"
                        required
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary"
                            >キャンセル</Button
                        >
                    </DialogClose>
                    <Button type="submit" :disabled="form.processing"
                        >次へ</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
