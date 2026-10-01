<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft } from '@tabler/icons-vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy,
    index,
    password,
    twoFactor,
    update,
} from '@/routes/admin/users';
import type { AdminUser } from '@/types';

const props = defineProps<{
    user: AdminUser;
    isSelf: boolean;
    // 管理者のうち最後の 1 人（降格・削除できない）
    isLastAdmin: boolean;
    passwordRules: string;
}>();

// ── プロフィールと権限 ─────────────────────────────────────
const profileForm = useForm({
    name: props.user.name,
    login_id: props.user.login_id,
    email: props.user.email ?? '',
    is_admin: props.user.is_admin,
});

function saveProfile() {
    profileForm.patch(update.url(props.user.id), { preserveScroll: true });
}

// ── パスワードの再設定 ─────────────────────────────────────
const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

function resetPassword() {
    passwordForm.put(password.url(props.user.id), {
        preserveScroll: true,
        onFinish: () => passwordForm.reset(),
    });
}

// ── 2 段階認証の解除・削除 ──────────────────────────────────
const processing = ref(false);
const deleteError = ref<string | null>(null);

function disableTwoFactor() {
    router.delete(twoFactor.url(props.user.id), {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

function deleteUser() {
    router.delete(destroy.url(props.user.id), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onError: (errors) => (deleteError.value = errors.user ?? null),
    });
}
</script>

<template>
    <Head :title="`${user.name}（ユーザーの編集）`" />

    <Link
        :href="index()"
        class="mb-4 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
    >
        <IconArrowLeft class="size-4" />
        ユーザー一覧
    </Link>

    <div class="max-w-xl space-y-10">
        <section class="space-y-6">
            <Heading
                variant="small"
                :title="`${user.name}（${user.login_id}）`"
                description="名前・ログイン ID・メールアドレス・権限を変更します"
            />

            <form class="space-y-6" @submit.prevent="saveProfile">
                <div class="grid gap-2">
                    <Label for="name">名前</Label>
                    <Input id="name" v-model="profileForm.name" required />
                    <InputError :message="profileForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="login_id">ログイン ID</Label>
                    <Input
                        id="login_id"
                        v-model="profileForm.login_id"
                        required
                        autocapitalize="none"
                        spellcheck="false"
                    />
                    <InputError :message="profileForm.errors.login_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">
                        メールアドレス
                        <span class="font-normal text-muted-foreground"
                            >（任意）</span
                        >
                    </Label>
                    <Input
                        id="email"
                        v-model="profileForm.email"
                        type="email"
                    />
                    <InputError :message="profileForm.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="is_admin" class="flex items-center gap-3">
                        <Checkbox
                            id="is_admin"
                            v-model="profileForm.is_admin"
                            :disabled="isLastAdmin"
                        />
                        <span
                            >管理者（ユーザー管理とサイト設定ができます）</span
                        >
                    </Label>
                    <p v-if="isLastAdmin" class="text-xs text-muted-foreground">
                        管理者が 1 人しかいないため、管理者の権限は外せません。
                    </p>
                    <InputError :message="profileForm.errors.is_admin" />
                </div>

                <Button type="submit" :disabled="profileForm.processing">
                    <Spinner v-if="profileForm.processing" />
                    保存する
                </Button>
            </form>
        </section>

        <Separator />

        <section class="space-y-6">
            <Heading
                variant="small"
                title="パスワードの再設定"
                description="新しいパスワードを設定し、本人に伝えてください。「ログインしたままにする」も解除されます"
            />

            <form class="space-y-6" @submit.prevent="resetPassword">
                <div class="grid gap-2">
                    <Label for="password">新しいパスワード</Label>
                    <PasswordInput
                        id="password"
                        v-model="passwordForm.password"
                        required
                        autocomplete="new-password"
                        :passwordrules="passwordRules"
                    />
                    <InputError :message="passwordForm.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation"
                        >新しいパスワード（確認用）</Label
                    >
                    <PasswordInput
                        id="password_confirmation"
                        v-model="passwordForm.password_confirmation"
                        required
                        autocomplete="new-password"
                        :passwordrules="passwordRules"
                    />
                    <InputError
                        :message="passwordForm.errors.password_confirmation"
                    />
                </div>

                <Button
                    type="submit"
                    variant="secondary"
                    :disabled="passwordForm.processing"
                >
                    <Spinner v-if="passwordForm.processing" />
                    パスワードを再設定する
                </Button>
            </form>
        </section>

        <template v-if="user.two_factor_enabled">
            <Separator />

            <section class="space-y-4">
                <Heading
                    variant="small"
                    title="2 段階認証の解除"
                    description="認証アプリの端末をなくし、リカバリーコードも無いときに使います。解除すると、パスワードだけでログインできるようになります"
                />
                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="secondary"
                            >2 段階認証を解除する</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle
                                >2 段階認証を解除しますか？</DialogTitle
                            >
                            <DialogDescription>
                                本人に確認したうえで解除してください。本人は個人設定から、もう一度2段階認証を設定できます。
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary">キャンセル</Button>
                            </DialogClose>
                            <Button
                                :disabled="processing"
                                @click="disableTwoFactor"
                                >解除する</Button
                            >
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </section>
        </template>

        <Separator />

        <section class="space-y-4">
            <Heading
                variant="small"
                title="ユーザーの削除"
                description="作成・編集したページと履歴は残り、編集者名は表示されなくなります"
            />
            <p v-if="isSelf" class="text-sm text-muted-foreground">
                自分自身は削除できません。個人設定から削除してください。
            </p>
            <p v-else-if="isLastAdmin" class="text-sm text-muted-foreground">
                管理者が 1 人しかいないため、削除できません。
            </p>
            <Dialog v-else>
                <DialogTrigger as-child>
                    <Button variant="destructive">ユーザーを削除する</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle
                            >「{{ user.name }}」を削除しますか？</DialogTitle
                        >
                        <DialogDescription>
                            削除したユーザーは元に戻せません。
                        </DialogDescription>
                    </DialogHeader>
                    <InputError :message="deleteError ?? undefined" />
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary">キャンセル</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            :disabled="processing"
                            @click="deleteUser"
                            >削除する</Button
                        >
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    </div>
</template>
