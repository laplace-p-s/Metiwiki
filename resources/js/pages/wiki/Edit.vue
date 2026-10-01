<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconBrackets,
    IconEye,
    IconEyeOff,
    IconFileText,
    IconPhotoPlus,
} from '@tabler/icons-vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    toRef,
    watch,
} from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import MarkdownToolbar from '@/components/wiki/MarkdownToolbar.vue';
import DeleteArticleButton from '@/components/wiki/DeleteArticleButton.vue';
import PageTabs from '@/components/wiki/PageTabs.vue';
import WikiBody from '@/components/wiki/WikiBody.vue';
import {
    ACCEPTED_IMAGE_TYPES,
    useImageUpload,
} from '@/composables/useImageUpload';
import { useMarkdownPreview } from '@/composables/useMarkdownPreview';
import type { ToolbarItem } from '@/composables/useMarkdownToolbar';
import { useMarkdownToolbar } from '@/composables/useMarkdownToolbar';
import { caretScreenPosition } from '@/lib/caretOffset';
import { store, update } from '@/routes/wiki';
import type { EditConflict } from '@/types';

const props = defineProps<{
    isNew: boolean;
    article: { id: number; title: string } | null;
    title: string;
    body: string;
    version: number;
    // タイトルを変更できない（ホームページとヘルプ）
    titleLocked: boolean;
    // WikiLink の補完候補（既存ページのタイトル。編集中のページ自身は除く）
    pageTitles: string[];
    // 既存ページのときは edit・history も渡される
    urls: { show: string; edit?: string; history?: string };
    // アップロードできる画像 1 枚の上限（バイト）
    uploadMaxBytes: number;
    // ページを削除できるか（新規作成とホームページでは false）
    canDelete: boolean;
}>();

const page = usePage();

const form = useForm({
    title: props.title,
    body: props.body,
    summary: '',
    version_number: props.version,
});

// ── 編集の競合 ──────────────────────────────────────────────
// 保存時に新しい版が見つかると、サーバーは編集内容を保ったまま元の画面へ戻し、最新版を flash で渡す。
// 最新版の版番号に合わせることで、内容を確認したうえで保存し直せるようにする
const conflict = ref<EditConflict | null>(null);

watch(
    () => page.flash.conflict,
    (value) => {
        if (value) {
            conflict.value = value;
            form.version_number = value.latestVersion;
        }
    },
    { immediate: true },
);

// ── 保存 ──────────────────────────────────────────────────
let submitting = false;

function submit() {
    submitting = true;
    const options = { onFinish: () => (submitting = false) };

    if (props.isNew || !props.article) {
        form.post(store.url(), options);
    } else {
        form.patch(update.url(props.article.id), options);
    }
}

// ── 未保存の変更がある状態で離れるときの確認 ──────────────────────
const LEAVE_MESSAGE = '保存していない変更があります。このページを離れますか？';

function onBeforeUnload(event: BeforeUnloadEvent) {
    if (form.isDirty && !submitting) {
        event.preventDefault();
    }
}

let removeBeforeListener: (() => void) | undefined;

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload);
    removeBeforeListener = router.on('before', (event) => {
        if (
            form.isDirty &&
            !submitting &&
            event.detail.visit.method === 'get' &&
            !window.confirm(LEAVE_MESSAGE)
        ) {
            event.preventDefault();
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload);
    removeBeforeListener?.();
});

// ── プレビュー ──────────────────────────────────────────────
const showPreview = ref(false);
const preview = useMarkdownPreview(toRef(form, 'body'), showPreview);

// ── ツールバー ──────────────────────────────────────────────
const bodyRef = ref<HTMLTextAreaElement | null>(null);

const { baseItems, insertWikiLink, handleShortcut } = useMarkdownToolbar(
    bodyRef,
    () => form.body,
    (value) => (form.body = value),
);

// ── 画像のアップロード（ツールバー・貼り付け・ドラッグ＆ドロップ）──────────
const fileInput = ref<HTMLInputElement | null>(null);

const images = useImageUpload(
    bodyRef,
    () => form.body,
    (value) => (form.body = value),
    props.uploadMaxBytes,
);

const uploadLimitLabel = `${(props.uploadMaxBytes / 1024 / 1024).toFixed(1)}MB`;

function onFilesSelected(event: Event) {
    const input = event.target as HTMLInputElement;

    if (input.files?.length) {
        void images.upload(input.files);
    }

    // 同じファイルをもう一度選べるようにする
    input.value = '';
}

// WikiLink・画像（Wiki 独自の操作）を先頭に置いたツールバー
const toolbarItems = computed<ToolbarItem[]>(() => [
    {
        icon: IconBrackets,
        title: 'WikiLink [[ページ名]]',
        action: props.pageTitles.length ? openLinkPicker : insertWikiLink,
    },
    {
        icon: IconPhotoPlus,
        title: `画像をアップロード（${uploadLimitLabel} まで。貼り付け・ドラッグでも可）`,
        action: () => fileInput.value?.click(),
    },
    ...baseItems.map((item, i) =>
        i === 0 ? { ...item, divider: true } : item,
    ),
]);

// ── WikiLink の補完（[[ を打つと既存ページの候補を出す）─────────────
const linkOpen = ref(false);
const linkQuery = ref('');
const linkStart = ref(0); // 本文中の '[[' の位置
const linkIndex = ref(0);
const linkPos = ref({ left: 0, top: 0 });
let closeTimer: ReturnType<typeof setTimeout> | undefined;

const linkCandidates = computed(() => {
    const q = linkQuery.value.trim().toLowerCase();

    if (!q) {
        return props.pageTitles.slice(0, 8);
    }

    // 前方一致を先に、その後に部分一致
    const starts: string[] = [];
    const includes: string[] = [];

    for (const t of props.pageTitles) {
        const lower = t.toLowerCase();

        if (lower.startsWith(q)) {
            starts.push(t);
        } else if (lower.includes(q)) {
            includes.push(t);
        }
    }

    return [...starts, ...includes].slice(0, 8);
});

function detectLink() {
    clearTimeout(closeTimer);
    const el = bodyRef.value;

    if (!el || !props.pageTitles.length) {
        linkOpen.value = false;

        return;
    }

    const pos = el.selectionStart ?? form.body.length;
    // カーソル直前に、まだ閉じていない [[ があるか（間に ] や改行・| を挟まない）
    const m = /\[\[([^[\]|\n]*)$/.exec(form.body.slice(0, pos));

    if (!m) {
        linkOpen.value = false;

        return;
    }

    linkQuery.value = m[1];
    linkStart.value = pos - m[1].length - 2;
    linkIndex.value = 0;
    linkOpen.value = linkCandidates.value.length > 0;

    if (linkOpen.value) {
        linkPos.value = caretScreenPosition(el, pos);
    }
}

function applyLink(title: string) {
    const el = bodyRef.value;
    const pos = el?.selectionEnd ?? form.body.length;
    // ツールバーから挿入した場合など、直後に既に ]] があればそれを使い回す
    const closing = form.body.slice(pos).startsWith(']]') ? 2 : 0;
    const insert = `[[${title}]]`;
    form.body =
        form.body.slice(0, linkStart.value) +
        insert +
        form.body.slice(pos + closing);
    linkOpen.value = false;

    const caret = linkStart.value + insert.length;
    void nextTick(() => {
        el?.focus();
        el?.setSelectionRange(caret, caret);
    });
}

// ツールバーの WikiLink ボタン: [[]] を挿入して中にカーソルを置き、そのまま候補を出す
function openLinkPicker() {
    const el = bodyRef.value;

    if (!el) {
        return;
    }

    const start = el.selectionStart ?? form.body.length;
    const end = el.selectionEnd ?? start;
    const selected = form.body.slice(start, end);
    form.body =
        form.body.slice(0, start) + `[[${selected}]]` + form.body.slice(end);

    const caret = start + 2 + selected.length;
    void nextTick(() => {
        el.focus();
        el.setSelectionRange(caret, caret);
        detectLink();
    });
}

function onBodyKeydown(event: KeyboardEvent) {
    if (linkOpen.value && linkCandidates.value.length) {
        const count = linkCandidates.value.length;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            linkIndex.value = (linkIndex.value + 1) % count;

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            linkIndex.value = (linkIndex.value - 1 + count) % count;

            return;
        }

        if (
            (event.key === 'Enter' || event.key === 'Tab') &&
            !event.isComposing
        ) {
            event.preventDefault();
            applyLink(linkCandidates.value[linkIndex.value]);

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            linkOpen.value = false;

            return;
        }
    }

    handleShortcut(event);
}

function onBodyInput() {
    void nextTick(detectLink);
}

// 候補のクリック（mousedown）より blur が先に来るため、閉じるのを少し遅らせる
function closeLinkSoon() {
    clearTimeout(closeTimer);
    closeTimer = setTimeout(() => (linkOpen.value = false), 120);
}
</script>

<template>
    <Head :title="isNew ? `${title}（新規作成）` : `${title}（編集）`" />

    <PageTabs
        v-if="!isNew && article"
        active="edit"
        :urls="{ view: urls.show, edit: urls.edit, history: urls.history }"
    >
        <template #actions>
            <DeleteArticleButton v-if="canDelete" :article-id="article.id" />
            <Button
                type="submit"
                form="article-edit-form"
                size="sm"
                :disabled="form.processing || images.uploading.value > 0"
                data-test="save-button-top"
            >
                <Spinner v-if="form.processing" />
                保存する
            </Button>
        </template>
    </PageTabs>
    <div v-else class="mb-4 flex items-start gap-3">
        <h1 class="min-w-0 flex-1 text-xl font-semibold break-words">
            新規作成: {{ title }}
        </h1>
        <Button variant="ghost" size="sm" class="shrink-0" as-child>
            <Link :href="urls.show">キャンセル</Link>
        </Button>
        <Button
            type="submit"
            form="article-edit-form"
            size="sm"
            class="shrink-0"
            :disabled="form.processing || images.uploading.value > 0"
            data-test="save-button-top"
        >
            <Spinner v-if="form.processing" />
            作成する
        </Button>
    </div>

    <!-- 編集の競合 -->
    <div
        v-if="conflict"
        class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100"
        role="alert"
        data-test="edit-conflict"
    >
        <div class="flex items-start gap-2.5">
            <IconAlertTriangle class="mt-0.5 size-5 shrink-0" />
            <div class="min-w-0">
                <p class="font-medium">編集が競合しました</p>
                <p class="mt-0.5">
                    編集している間に
                    <template v-if="conflict.updatedBy"
                        >{{ conflict.updatedBy }} さんが</template
                    >
                    新しい版を保存しました。あなたの編集内容は下に残っています。最新版の内容を確認し、必要なら取り込んでから、もう一度保存してください。
                </p>
            </div>
        </div>
        <details class="mt-3">
            <summary class="cursor-pointer text-xs hover:underline">
                最新版（v{{ conflict.latestVersion }}）の本文を表示
            </summary>
            <pre
                class="mt-2 max-h-72 overflow-y-auto rounded border bg-background p-3 text-xs whitespace-pre-wrap text-foreground"
                >{{ conflict.latestBody }}</pre>
        </details>
    </div>

    <!-- 保存ボタンは下端に加えて上部（タブの行・見出しの横）にもあり、form 属性でこのフォームを送信する -->
    <form id="article-edit-form" class="space-y-5" @submit.prevent="submit">
        <!-- タイトル -->
        <div class="grid gap-2">
            <Label for="title">タイトル</Label>
            <Input
                id="title"
                v-model="form.title"
                :readonly="titleLocked"
                :class="{ 'bg-muted text-muted-foreground': titleLocked }"
                placeholder="ページタイトル"
                autocomplete="off"
                required
            />
            <InputError :message="form.errors.title" />
            <p
                v-if="titleLocked && !form.errors.title"
                class="text-xs text-muted-foreground"
            >
                このページのタイトルは変更できません。
            </p>
            <p
                v-else-if="!isNew && form.title !== title && !form.errors.title"
                class="text-xs text-muted-foreground"
            >
                タイトルを変えると、元のタイトルのURLやリンクは新しいタイトルへ転送されます。
            </p>
        </div>

        <!-- 本文 -->
        <div class="grid gap-2">
            <div class="flex items-center justify-between">
                <Label for="body">本文</Label>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 gap-1 text-xs"
                    @click="showPreview = !showPreview"
                >
                    <component
                        :is="showPreview ? IconEyeOff : IconEye"
                        class="size-3.5"
                    />
                    {{ showPreview ? 'プレビューを閉じる' : 'プレビュー' }}
                </Button>
            </div>

            <div class="grid gap-3" :class="{ 'lg:grid-cols-2': showPreview }">
                <div
                    class="overflow-hidden rounded-md border bg-card shadow-xs"
                >
                    <MarkdownToolbar :items="toolbarItems" />
                    <textarea
                        id="body"
                        ref="bodyRef"
                        v-model="form.body"
                        rows="24"
                        class="block w-full resize-y border-0 bg-field px-3 py-2 font-mono text-sm leading-relaxed focus:outline-none focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset"
                        placeholder="# 見出し&#10;&#10;本文を入力します。他のページへは [[ページ名]] でリンクできます。"
                        @keydown="onBodyKeydown"
                        @input="onBodyInput"
                        @click="detectLink"
                        @blur="closeLinkSoon"
                        @paste="images.onPaste"
                        @dragover="images.onDragOver"
                        @drop="images.onDrop"
                    ></textarea>
                    <input
                        ref="fileInput"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="ACCEPTED_IMAGE_TYPES.join(',')"
                        @change="onFilesSelected"
                    />
                </div>

                <div
                    v-if="showPreview"
                    class="relative max-h-[40rem] min-h-40 overflow-y-auto rounded-md border bg-card px-5 py-3 text-card-foreground"
                    aria-live="polite"
                    data-test="preview"
                >
                    <Spinner
                        v-if="preview.loading.value"
                        class="absolute top-2 right-2 size-4 text-muted-foreground"
                    />
                    <p
                        v-if="preview.error.value"
                        class="text-sm text-destructive"
                    >
                        {{ preview.error.value }}
                    </p>
                    <WikiBody v-else :html="preview.html.value" />
                </div>
            </div>

            <!-- WikiLink の補完（textarea の overflow に隠れないよう body へ出して fixed で置く） -->
            <Teleport to="body">
                <ul
                    v-if="linkOpen"
                    :style="{
                        left: `${linkPos.left}px`,
                        top: `${linkPos.top}px`,
                    }"
                    class="fixed z-50 max-h-56 w-72 overflow-auto rounded-md border bg-popover py-1 text-sm text-popover-foreground shadow-lg"
                    role="listbox"
                >
                    <li
                        v-for="(t, i) in linkCandidates"
                        :key="t"
                        role="option"
                        :aria-selected="i === linkIndex"
                        class="flex cursor-pointer items-center gap-2 px-3 py-1.5"
                        :class="{ 'bg-accent': i === linkIndex }"
                        @mousedown.prevent="applyLink(t)"
                        @mouseenter="linkIndex = i"
                    >
                        <IconFileText
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                        <span class="truncate">{{ t }}</span>
                    </li>
                </ul>
            </Teleport>

            <p
                v-if="images.uploading.value"
                class="flex items-center gap-2 text-xs text-muted-foreground"
                aria-live="polite"
            >
                <Spinner class="size-3.5" />
                画像をアップロードしています…
            </p>
            <p v-else class="text-xs text-muted-foreground">
                画像は貼り付けやドラッグ＆ドロップでも追加できます（JPEG・PNG・GIF・WebP、{{
                    uploadLimitLabel
                }}
                まで）。
            </p>

            <InputError :message="form.errors.body" />
            <InputError :message="form.errors.version_number" />
        </div>

        <!-- 編集要約 -->
        <div class="grid gap-2">
            <Label for="summary">編集要約（任意）</Label>
            <Input
                id="summary"
                v-model="form.summary"
                maxlength="255"
                placeholder="変更内容の概要（最近の更新・履歴に表示されます）"
                autocomplete="off"
            />
            <InputError :message="form.errors.summary" />
        </div>

        <div class="flex items-center justify-end gap-2">
            <!-- 既存ページは閲覧・履歴のタブで離れられるので、キャンセルはタブの無い新規作成だけに置く -->
            <Button v-if="isNew" variant="ghost" as-child>
                <Link :href="urls.show">キャンセル</Link>
            </Button>
            <Button
                type="submit"
                :disabled="form.processing || images.uploading.value > 0"
                data-test="save-button"
            >
                <Spinner v-if="form.processing" />
                {{ isNew ? '作成する' : '保存する' }}
            </Button>
        </div>
    </form>
</template>
