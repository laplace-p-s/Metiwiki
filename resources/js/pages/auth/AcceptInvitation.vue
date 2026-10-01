<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, login } from '@/routes';
import { store } from '@/routes/invitation';

defineProps<{
    token: string;
    // 招待が未使用かつ有効期限内か
    valid: boolean;
    passwordRules: string;
    passwordRequirements: string;
}>();

defineOptions({
    layout: {
        title: 'アカウント登録',
        description: '招待リンクからアカウントを作成します',
    },
});
</script>

<template>
    <Head title="アカウント登録" />

    <div v-if="!valid" class="space-y-4 text-center text-sm">
        <p class="text-muted-foreground">
            この招待リンクは使用済みか、有効期限が切れています。管理者に新しい招待リンクを発行してもらってください。
        </p>
        <TextLink :href="home()">トップページへ</TextLink>
    </div>

    <Form
        v-else
        v-bind="store.form(token)"
        :reset-on-error="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <InputError :message="errors.invitation" />

        <p class="-mb-2 text-xs text-muted-foreground">
            <span class="text-destructive">*</span>は必須項目です
        </p>

        <div class="grid gap-2">
            <Label for="name">
                <span>名前<RequiredMark /></span>
                <span class="text-xs font-normal text-muted-foreground"
                    >255文字以内、編集履歴などに表示されます</span
                >
            </Label>
            <Input id="name" name="name" required v-focus autocomplete="name" />
            <InputError :message="errors.name" />
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
                name="login_id"
                required
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
            />
            <InputError :message="errors.login_id" />
        </div>

        <div class="grid gap-2">
            <Label for="email">メールアドレス</Label>
            <Input id="email" name="email" type="email" autocomplete="email" />
            <InputError :message="errors.email" />
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
                name="password"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">
                <span>パスワード（確認用）<RequiredMark /></span>
            </Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            登録する
        </Button>

        <div class="text-center text-sm text-muted-foreground">
            アカウントをお持ちの方は
            <TextLink :href="login()">ログイン</TextLink>
        </div>
    </Form>
</template>
