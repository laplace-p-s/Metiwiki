<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { IconCheck } from '@tabler/icons-vue';
import { onBeforeUnmount, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/admin/settings';

const props = defineProps<{
    siteName: string;
    registrationEnabled: boolean;
    themeColor: string;
    themeColors: { value: string; label: string }[];
    passwordResetEnabled: boolean;
}>();

const form = useForm({
    site_name: props.siteName,
    registration_enabled: props.registrationEnabled,
    theme_color: props.themeColor,
});

function submit() {
    form.patch(update.url(), { preserveScroll: true });
}

// 選んだ色をその場で画面全体に反映して見比べられるようにする（保存するまでは仮の表示）
watch(
    () => form.theme_color,
    (color) => (document.documentElement.dataset.theme = color),
);

const page = usePage();

// 保存せずに離れたら、保存済みの色に戻す（保存した場合は共有 props が新しい色になっている）
onBeforeUnmount(() => {
    document.documentElement.dataset.theme = page.props.themeColor;
});
</script>

<template>
    <Head title="サイト設定" />

    <Heading
        variant="small"
        title="サイト設定"
        description="Wiki 全体に関わる設定です"
    />

    <form class="mt-6 max-w-xl space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="site_name">Wiki 名</Label>
            <Input id="site_name" v-model="form.site_name" required />
            <p class="text-xs text-muted-foreground">
                ヘッダーとブラウザのタブに表示されます。
            </p>
            <InputError :message="form.errors.site_name" />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm leading-none font-medium">
                テーマ色
            </legend>
            <div
                class="flex flex-wrap gap-2"
                role="radiogroup"
                aria-label="テーマ色"
            >
                <label
                    v-for="color in themeColors"
                    :key="color.value"
                    :title="color.label"
                    class="relative cursor-pointer"
                >
                    <input
                        v-model="form.theme_color"
                        type="radio"
                        name="theme_color"
                        :value="color.value"
                        class="peer sr-only"
                    />
                    <!-- 色見本。data-theme を付けると、その色の primary がライト/ダークに合わせて入る -->
                    <span
                        :data-theme="color.value"
                        class="flex size-8 items-center justify-center rounded-full bg-primary text-primary-foreground ring-offset-2 ring-offset-background peer-focus-visible:ring-2 peer-focus-visible:ring-foreground"
                        :class="{
                            'ring-2 ring-foreground':
                                form.theme_color === color.value,
                        }"
                    >
                        <IconCheck
                            v-if="form.theme_color === color.value"
                            class="size-4"
                        />
                    </span>
                    <span class="sr-only">{{ color.label }}</span>
                </label>
            </div>
            <p class="text-xs text-muted-foreground">
                ヘッダーやボタンをこの色で塗り、背景や枠線もこの色味に寄せます。ライト/ダークのどちらでも読みやすい濃さに自動で調整されます。選ぶと画面に仮に反映され、保存すると全員の画面に反映されます。
            </p>
            <InputError :message="form.errors.theme_color" />
        </fieldset>

        <div class="grid gap-2">
            <Label for="registration_enabled" class="flex items-center gap-3">
                <Checkbox
                    id="registration_enabled"
                    v-model="form.registration_enabled"
                />
                <span>誰でもアカウントを登録できるようにする</span>
            </Label>
            <p class="text-xs text-muted-foreground">
                オフのときは、管理者が作成したユーザーと、招待リンクから登録した人だけが編集できます。インターネットに公開している場合は、荒らしを防ぐためオフを勧めます。
            </p>
            <InputError :message="form.errors.registration_enabled" />
        </div>

        <div class="rounded-lg border bg-muted/40 p-4 text-sm">
            <p class="font-medium">
                メールによるパスワードの再設定:
                {{ passwordResetEnabled ? '有効' : '無効' }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                .env
                でメールの送信（MAIL_MAILERなど）を設定すると有効になります。無効のときは、管理者がユーザーの編集画面からパスワードを再設定してください。
            </p>
        </div>

        <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            保存する
        </Button>
    </form>
</template>
