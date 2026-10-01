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
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
    passwordRequirements: string;
}>();

defineOptions({
    layout: {
        title: 'アカウント登録',
        description: '登録するとページを作成・編集できるようになります',
    },
});
</script>

<template>
    <Head title="アカウント登録" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-6">
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
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                />
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
                    type="text"
                    required
                    :tabindex="2"
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                    name="login_id"
                />
                <InputError :message="errors.login_id" />
            </div>

            <div class="grid gap-2">
                <Label for="email">メールアドレス</Label>
                <Input
                    id="email"
                    type="email"
                    :tabindex="3"
                    autocomplete="email"
                    name="email"
                />
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
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password"
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
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="6"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                登録する
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            アカウントをお持ちの方は
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
                >ログイン</TextLink
            >
        </div>
    </Form>
</template>
