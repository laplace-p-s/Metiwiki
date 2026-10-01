import type { Ref } from 'vue';
import { nextTick, ref } from 'vue';
import { toast } from 'vue-sonner';
import { apiHeaders, firstErrorMessage } from '@/lib/http';
import { store } from '@/routes/wiki/uploads';

// サーバー（UploadService）と同じ形式だけを受け付ける。SVG は不可
export const ACCEPTED_IMAGE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

function formatMegabytes(bytes: number): string {
    return `${(bytes / 1024 / 1024).toFixed(1)}MB`;
}

/**
 * 編集画面の画像アップロード（ファイル選択・貼り付け・ドラッグ＆ドロップ）。
 * アップロード中はカーソル位置に仮の記法を入れておき、完了したら画像の Markdown に置き換える。
 */
export function useImageUpload(
    textareaRef: Ref<HTMLTextAreaElement | null>,
    getValue: () => string,
    setValue: (value: string) => void,
    maxBytes: number,
) {
    const uploading = ref(0);
    let sequence = 0;

    // カーソル位置に文字列を挿入し、挿入後のカーソルをその直後に置く
    function insertAtCursor(text: string) {
        const el = textareaRef.value;
        const value = getValue();
        const start = el?.selectionStart ?? value.length;
        const end = el?.selectionEnd ?? start;

        setValue(value.slice(0, start) + text + value.slice(end));

        const caret = start + text.length;
        void nextTick(() => {
            el?.focus();
            el?.setSelectionRange(caret, caret);
        });
    }

    function replacePlaceholder(placeholder: string, replacement: string) {
        setValue(getValue().replace(placeholder, replacement));
    }

    async function uploadOne(file: File) {
        if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
            toast.error(
                `「${file.name}」はアップロードできません。JPEG・PNG・GIF・WebP の画像だけアップロードできます。`,
            );

            return;
        }

        if (file.size > maxBytes) {
            toast.error(
                `「${file.name}」は大きすぎます。画像は ${formatMegabytes(maxBytes)} 以下にしてください。`,
            );

            return;
        }

        const placeholder = `![アップロード中…(${++sequence})]()`;
        insertAtCursor(placeholder);
        uploading.value++;

        try {
            const body = new FormData();
            body.append('file', file);

            const response = await fetch(store.url(), {
                method: 'POST',
                headers: apiHeaders(),
                body,
                credentials: 'same-origin',
            });

            if (!response.ok) {
                const message =
                    response.status === 413
                        ? `画像が大きすぎます（${formatMegabytes(maxBytes)} まで）。`
                        : response.status === 429
                          ? 'アップロードが多すぎます。少し待ってから再度お試しください。'
                          : ((await firstErrorMessage(response)) ??
                            '画像をアップロードできませんでした。');

                replacePlaceholder(placeholder, '');
                toast.error(`「${file.name}」: ${message}`);

                return;
            }

            const result = (await response.json()) as { markdown: string };
            replacePlaceholder(placeholder, result.markdown);
        } catch {
            replacePlaceholder(placeholder, '');
            toast.error(`「${file.name}」をアップロードできませんでした。`);
        } finally {
            uploading.value--;
        }
    }

    async function upload(files: FileList | File[]) {
        // 挿入順を保つため 1 枚ずつ送る
        for (const file of Array.from(files)) {
            await uploadOne(file);
        }
    }

    function imagesFrom(list: DataTransfer | null): File[] {
        return Array.from(list?.files ?? []).filter((f) =>
            f.type.startsWith('image/'),
        );
    }

    function onPaste(event: ClipboardEvent) {
        const files = imagesFrom(event.clipboardData);

        if (files.length) {
            event.preventDefault();
            void upload(files);
        }
    }

    // ファイルを運んでいるときだけドロップを受け付ける（文字のドラッグ移動は邪魔しない）
    function onDragOver(event: DragEvent) {
        if (event.dataTransfer?.types.includes('Files')) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'copy';
        }
    }

    function onDrop(event: DragEvent) {
        const files = imagesFrom(event.dataTransfer);

        if (files.length) {
            event.preventDefault();
            textareaRef.value?.focus();
            void upload(files);
        }
    }

    return { uploading, upload, onPaste, onDragOver, onDrop };
}
