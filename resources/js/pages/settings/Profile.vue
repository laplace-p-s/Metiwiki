<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { User } from '@/types';

const page = usePage();
// 個人設定はログイン必須の画面なので、ユーザーは必ずいる
const user = computed(() => page.props.auth.user as User);
</script>

<template>
    <Head title="プロフィール" />

    <h1 class="sr-only">プロフィール</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="プロフィール"
            description="名前・ログイン ID・メールアドレスを変更します"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
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
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="errors.name" />
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
                    class="mt-1 block w-full"
                    name="login_id"
                    :default-value="user.login_id"
                    required
                    autocomplete="username"
                    autocapitalize="none"
                    spellcheck="false"
                />
                <InputError class="mt-2" :message="errors.login_id" />
            </div>

            <div class="grid gap-2">
                <Label for="email">メールアドレス</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email ?? ''"
                    autocomplete="email"
                />
                <p class="text-xs text-muted-foreground">
                    パスワードを忘れたときの再設定に使います（サイトでメール送信が設定されている場合）。他の利用者には表示されません。
                </p>
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >保存する</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
