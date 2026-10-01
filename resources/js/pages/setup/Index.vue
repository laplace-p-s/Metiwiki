<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/setup';

defineProps<{
    passwordRules: string;
    passwordRequirements: string;
}>();

defineOptions({
    layout: {
        title: 'Metiwiki の初期設定',
        description: 'Wiki の名前と、最初の管理者アカウントを決めてください',
    },
});
</script>

<template>
    <Head title="初期設定" />

    <Form
        v-bind="store.form()"
        :reset-on-error="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <p class="-mb-2 text-xs text-muted-foreground">
            <span class="text-destructive">*</span>は必須項目です
        </p>

        <div class="grid gap-2">
            <Label for="site_name">
                <span>Wiki 名<RequiredMark /></span>
                <span class="text-xs font-normal text-muted-foreground"
                    >100文字以内</span
                >
            </Label>
            <Input
                id="site_name"
                name="site_name"
                default-value="Metiwiki"
                required
                v-focus
            />
            <p class="text-xs text-muted-foreground">
                あとから管理画面のサイト設定で変更できます。
            </p>
            <InputError :message="errors.site_name" />
        </div>

        <div class="grid gap-2">
            <Label for="sample_pages" class="flex items-center gap-3">
                <Checkbox
                    id="sample_pages"
                    name="sample_pages"
                    :default-value="true"
                />
                <span>使い方のサンプルページを作成する</span>
            </Label>
            <p class="text-xs text-muted-foreground">
                ホームからリンクする「Wikiの使い方」「Markdown記法」「サンプルページ」を作ります。外すとホームだけを作ります。
            </p>
        </div>

        <Separator />

        <div class="grid gap-2">
            <Label for="name">
                <span>管理者の名前<RequiredMark /></span>
                <span class="text-xs font-normal text-muted-foreground"
                    >255文字以内、編集履歴などに表示されます</span
                >
            </Label>
            <Input id="name" name="name" required autocomplete="name" />
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
            初期設定を完了する
        </Button>
    </Form>
</template>
