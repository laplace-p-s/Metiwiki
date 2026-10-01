<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/admin/users';

defineProps<{
    passwordRules: string;
    passwordRequirements: string;
}>();

const form = useForm({
    name: '',
    login_id: '',
    email: '',
    password: '',
    password_confirmation: '',
    is_admin: false,
});

function submit() {
    form.post(store.url(), {
        onError: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="ユーザーを作成" />

    <Heading
        variant="small"
        title="ユーザーを作成"
        description="作成したログイン ID とパスワードを本人に伝えてください"
    />

    <form class="mt-6 max-w-xl space-y-6" @submit.prevent="submit">
        <p class="mb-4 text-xs text-muted-foreground">
            <span class="text-destructive">*</span>は必須項目です
        </p>

        <div class="grid gap-2">
            <Label for="name">
                <span>名前<RequiredMark /></span>
                <span class="text-xs font-normal text-muted-foreground"
                    >255文字以内、編集履歴などに表示されます</span
                >
            </Label>
            <Input id="name" v-model="form.name" required autocomplete="off" />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="login_id">
                <span>ログイン ID<RequiredMark /></span>
                <span class="text-xs font-normal text-muted-foreground"
                    >半角英数字・ハイフン・下線、64文字以内</span
                >
            </Label>
            <Input
                id="login_id"
                v-model="form.login_id"
                required
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
            />
            <InputError :message="form.errors.login_id" />
        </div>

        <div class="grid gap-2">
            <Label for="email">メールアドレス</Label>
            <Input
                id="email"
                v-model="form.email"
                type="email"
                autocomplete="off"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password">
                <span>パスワード<RequiredMark /></span>
                <span class="text-xs font-normal text-muted-foreground">{{
                    passwordRequirements
                }}</span>
            </Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="form.errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">
                <span>パスワード（確認用）<RequiredMark /></span>
            </Label>
            <PasswordInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="form.errors.password_confirmation" />
        </div>

        <Label for="is_admin" class="flex items-center gap-3">
            <Checkbox id="is_admin" v-model="form.is_admin" />
            <span>管理者にする（ユーザー管理とサイト設定ができます）</span>
        </Label>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                作成する
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="index()">キャンセル</Link>
            </Button>
        </div>
    </form>
</template>
